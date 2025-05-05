<?php
namespace Antler\ErrorLogger;

use DateTime;
use ErrorException;
use RuntimeException;
use Throwable;

class Logger
{
    private static $instance = null;
    private $config;
    private $logCounts = [];

    /**
     * Private constructor to enforce singleton pattern
     *
     * @param LoggerConfig $config
     */
    private function __construct(LoggerConfig $config)
    {
        $this->config = $config;

        if (!$this->config->getProjectHash()) {
            throw new RuntimeException('Project hash is not configured!');
        }

        if ($this->config->useRemoteLogging() && !$this->config->getRemoteEndpoint()) {
            throw new RuntimeException('Remote logging is not configured!');
        }

        // Setup error handlers
        $this->setupErrorHandlers();

        // Create log directory if it doesn't exist and file logging is enabled
        if ($this->config->useFileLogging()) {
            $this->ensureLogDirectoryExists();
        }
    }

    /**
     * Create log directory if it doesn't exist
     */
    private function ensureLogDirectoryExists(): void
    {
        $logDir = dirname($this->config->getLogFilePath());
        if (!is_dir($logDir)) {
            $created = @mkdir($logDir, 0755, true);
            if (!$created) {
                // Log failure using error_log since file logging isn't available yet
                error_log("Failed to create log directory: $logDir");
            }
        }

        // Check if the directory is writable
        if (!is_writable($logDir)) {
            error_log("Log directory is not writable: $logDir");
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(LoggerConfig $config = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config ?? new LoggerConfig());
        }
        return self::$instance;
    }

    /**
     * Setup PHP error handlers
     */
    private function setupErrorHandlers(): void
    {
        // Exception handler
        set_exception_handler(function (Throwable $e): void {
            $this->critical(
                $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        });

        // Error handler
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) return false;

            // PHP 7 compatible error level matching
            $level = LogLevel::ERROR;
            if ($severity === E_NOTICE || $severity === E_USER_NOTICE) {
                $level = LogLevel::INFO;
            } elseif (in_array($severity, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true)) {
                $level = LogLevel::WARNING;
            }

            $this->log(
                $level,
                $message,
                [
                    'file' => $file,
                    'line' => $line,
                    'trace' => (new ErrorException($message, 0, $severity, $file, $line))->getTraceAsString()
                ]
            );

            return true;
        });

        // Shutdown handler for fatal errors
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->critical(
                    $error['message'],
                    [
                        'file' => $error['file'],
                        'line' => $error['line']
                    ]
                );
            }
        });
    }

    /**
     * Check if we should rate limit this log entry
     */
    private function shouldRateLimit(int $level): bool
    {
        $minute = date('YmdHi');

        if (!isset($this->logCounts[$minute])) {
            $this->logCounts = [$minute => 1]; // Reset and start new minute
            return false;
        }

        if ($this->logCounts[$minute]++ >= $this->config->getRateLimitPerMinute()) {
            // Only log the rate limiting if we haven't already
            if ($this->logCounts[$minute] === $this->config->getRateLimitPerMinute() + 1) {
                $this->writeToErrorLog("Log rate limit reached for this minute", LogLevel::WARNING);
            }
            return true;
        }

        return false;
    }

    /**
     * Format log entry to string
     */
    private function formatLogEntry(int $level, string $message, array $context = []): string
    {
        $timestamp = (new DateTime())->format(DateTime::ATOM);
        $levelStr = LogLevel::toString($level);

        $contextStr = '';
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return "[$timestamp] [$levelStr] $message" . ($contextStr ? " $contextStr" : "");
    }

    /**
     * Write to PHP error log
     */
    private function writeToErrorLog(string $message, int $level): void
    {
        if ($this->config->useErrorLog()) {
            error_log("[" . LogLevel::toString($level) . "] " . $message);
        }
    }

    /**
     * Write to file
     */
    private function writeToFile(string $formattedMessage): void
    {
        if (!$this->config->useFileLogging()) {
            return;
        }

        $logFilePath = $this->config->getLogFilePath();

        try {
            $result = file_put_contents(
                $logFilePath,
                $formattedMessage . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

            if ($result === false) {
                $this->writeToErrorLog(
                    "Failed to write to log file: $logFilePath - Check permissions and disk space",
                    LogLevel::ERROR
                );
            }
        } catch (Throwable $e) {
            $this->writeToErrorLog("Failed to write to log file: {$e->getMessage()}", LogLevel::ERROR);
        }
    }

    /**
     * Retrieve all HTTP request headers from the server variables.
     *
     * @return array An associative array of HTTP headers where the keys are the header names in a human-readable format and the values are the corresponding header values.
     */
    function getAllHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    /**
     * Send log to remote endpoint
     */
    private function sendToRemote(int $level, string $message, array $context = []): void
    {
        if (!$this->config->useRemoteLogging()) {
            return;
        }

        $headers = array_map(function ($value) {
            return $value;
        }, $this->getAllHeaders());

        $requestBody = !empty($_POST)
            ? $_POST
            : json_decode(file_get_contents('php://input'), true);


        $payload = [
            'project_hash' => $this->config->getProjectHash(),
            'level' => $level,
            'level_name' => LogLevel::toString($level),
            'message' => $message,
            'context' => $context,
            'timestamp' => (new DateTime())->format(DateTime::ATOM),
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'server_name' => $_SERVER['SERVER_NAME'] ?? gethostname() ?: null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'environment' => getenv('APP_ENV') ?: 'production',
            'headers' => $headers,
            'query_params' => $_GET,
            'request_body' => $requestBody,
        ];

        try {
            // PHP 7 compatible JSON encoding (no JSON_THROW_ON_ERROR)
            $jsonPayload = json_encode($payload);
            if ($jsonPayload === false) {
                throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
            }

            $this->sendViaCurl($jsonPayload) || $this->sendViaStream($jsonPayload);
        } catch (Throwable $e) {
            $this->writeToErrorLog("Error reporting failed: {$e->getMessage()}", LogLevel::ERROR);
        }
    }

    /**
     * Send via cURL
     */
    private function sendViaCurl(string $jsonPayload): bool
    {
        if (!function_exists('curl_init')) return false;

        $ch = curl_init($this->config->getRemoteEndpoint());

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $this->config->getRequestTimeout(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => true,
        ]);

        try {
            $response = curl_exec($ch);
            if ($response === false || curl_errno($ch)) {
                throw new RuntimeException('cURL error: ' . curl_error($ch));
            }
            return true;
        } catch (Throwable $e) {
            $this->writeToErrorLog("cURL send failed: {$e->getMessage()}", LogLevel::ERROR);
            return false;
        } finally {
            curl_close($ch);
        }
    }

    /**
     * Send via file_get_contents/stream
     */
    private function sendViaStream(string $jsonPayload): bool
    {
        if (!ini_get('allow_url_fopen')) {
            $this->writeToErrorLog('Cannot send error: cURL unavailable and allow_url_fopen is disabled', LogLevel::ERROR);
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $jsonPayload,
                'timeout' => $this->config->getRequestTimeout()
            ]
        ]);

        try {
            $response = @file_get_contents($this->config->getRemoteEndpoint(), false, $context);
            return $response !== false;
        } catch (Throwable $e) {
            $this->writeToErrorLog("Stream send failed: {$e->getMessage()}", LogLevel::ERROR);
            return false;
        }
    }

    /**
     * Main log method
     */
    public function log(int $level, string $message, array $context = []): void
    {
        // Check minimum log level
        if ($level < $this->config->getMinLogLevel()) {
            return;
        }

        // Check rate limiting
        if ($this->shouldRateLimit($level)) {
            return;
        }

        // Format log entry
        $formattedMessage = $this->formatLogEntry($level, $message, $context);

        // Log to various outputs
        $this->writeToFile($formattedMessage);
        $this->sendToRemote($level, $message, $context);

        // For high-severity logs, also use error_log as a fallback
        if ($level >= LogLevel::ERROR) {
            $this->writeToErrorLog($message, $level);
        }
    }

    /**
     * Debug level log
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Info level log
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Warning level log
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Error level log
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Critical level log
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }
}

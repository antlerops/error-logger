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
    private $isCli;
    private $startTime;
    private $memoryUsageStart;

    /**
     * Private constructor to enforce singleton pattern
     *
     * @param LoggerConfig $config
     */
    private function __construct(LoggerConfig $config)
    {
        $this->config = $config;
        $this->isCli = php_sapi_name() === 'cli';
        $this->startTime = microtime(true);
        $this->memoryUsageStart = memory_get_usage(true);

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
     *
     * @param LoggerConfig|null $config Configuration for the logger
     * @return self
     */
    public static function getInstance(?LoggerConfig $config = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config ?? new LoggerConfig());
        }
        return self::$instance;
    }

    /**
     * Reset instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Get the current configuration
     */
    public function getConfig(): LoggerConfig
    {
        return $this->config;
    }

    /**
     * Sets up custom error, exception, and shutdown handlers to log and manage application errors.
     *
     * @return void
     */
    private function setupErrorHandlers(): void
    {
        // Exception handler
        set_exception_handler(function (Throwable $e): void {
            // Get enhanced stack trace with code context
            $enhancedTrace = CodeFrame::enhancedTraceFromException($e);

            $this->critical(
                $e->getMessage(),
                [
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'enhanced_trace' => $enhancedTrace,
                    'trace' => $e->getTraceAsString() // Keep the original trace for backward compatibility
                ]
            );
        });

        // Error handler
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) return false;

            // PHP 7 compatible error level matching
            $level = LogLevel::ERROR;
            if ($severity === E_NOTICE || $severity === E_USER_NOTICE || $severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                $level = LogLevel::INFO;
            } elseif (in_array($severity, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true)) {
                // Removed E_STRICT from this array
                $level = LogLevel::WARNING;
            }

            // Add code context for the error
            $codeContext = CodeFrame::getContext($file, $line);
            $enhancedTrace = CodeFrame::enhancedTraceFromError($file, $line, $message, $severity);

            $this->log(
                $level,
                $message,
                [
                    'error_type' => $this->getErrorTypeName($severity),
                    'error_severity' => $severity,
                    'file' => $file,
                    'line' => $line,
                    'code_context' => $codeContext,
                    'enhanced_trace' => $enhancedTrace,
                    'trace' => (new ErrorException($message, 0, $severity, $file, $line))->getTraceAsString()
                ]
            );

            return true;
        });

        // Shutdown handler for fatal errors
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                // Add code context for fatal errors if possible
                $codeContext = CodeFrame::getContext($error['file'], $error['line']);
                $enhancedTrace = null;

                try {
                    $enhancedTrace = CodeFrame::enhancedTraceFromError($error['file'], $error['line'], $error['message'], $error['type']);
                } catch (Throwable $e) {
                    // Cannot get enhanced trace, continue without it
                }

                $this->critical(
                    $error['message'],
                    [
                        'error_type' => $this->getErrorTypeName($error['type']),
                        'error_severity' => $error['type'],
                        'file' => $error['file'],
                        'line' => $error['line'],
                        'code_context' => $codeContext,
                        'enhanced_trace' => $enhancedTrace,
                        'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                        'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
                        'execution_time' => round(microtime(true) - $this->startTime, 4) . 's'
                    ]
                );
            }
        });
    }

    /**
     * Get human-readable error type name
     */
    private function getErrorTypeName(int $type): string
    {
        static $errorTypes = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];

        if (PHP_VERSION_ID < 80000 && defined('E_STRICT')) {
            $errorTypes[E_STRICT] = 'E_STRICT';
        } else if (PHP_VERSION_ID >= 80000) {
            // For PHP 8+, add E_STRICT's value (32) with a different approach
            // to avoid using the deprecated constant directly
            $errorTypes[32] = 'E_STRICT';
        }

        return $errorTypes[$type] ?? 'UNKNOWN_ERROR_TYPE';
    }

    // Rest of the class remains unchanged...
    /**
     * Format bytes to human-readable string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
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
     * @return array An associative array of HTTP headers
     */
    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    /**
     * Get request body safely
     *
     * @return array|null Request body as array or null if not available/applicable
     */
    private function getRequestBody(): ?array
    {
        if ($this->isCli) {
            return null;
        }

        // Don't try to parse body for GET/HEAD requests
        if (isset($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'])) {
            return null;
        }

        if (!empty($_POST)) {
            return $this->sanitizeData($_POST);
        }

        // Try to parse request body as JSON
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $json = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $this->sanitizeData($json);
            }
            // If not valid JSON but body exists, return truncated version
            if (strlen($input) > 0) {
                return ['_raw_body_preview' => substr($input, 0, 1000) . (strlen($input) > 1000 ? '...' : '')];
            }
        }

        return null;
    }

    /**
     * Sanitize sensitive data from arrays (passwords, tokens, etc.)
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'passwd', 'secret', 'token', 'auth', 'key', 'apikey', 'api_key',
            'access_token', 'accesstoken', 'credential', 'private', 'ssn', 'social_security',
            'cc', 'card', 'credit', 'cvv', 'cvc'
        ];

        $result = [];
        foreach ($data as $key => $value) {
            // Check for sensitive keys
            $keyLower = strtolower((string)$key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $pattern) {
                if (strpos($keyLower, $pattern) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitizeData($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        $currentMemory = memory_get_usage(true);
        $memoryGrowth = $currentMemory - $this->memoryUsageStart;

        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'memory_usage' => $this->formatBytes($currentMemory),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_growth' => $this->formatBytes($memoryGrowth),
            'memory_limit' => ini_get('memory_limit'),
            'execution_time' => round(microtime(true) - $this->startTime, 4) . 's',
            'max_execution_time' => ini_get('max_execution_time'),
            'timezone' => date_default_timezone_get(),
            'sapi' => php_sapi_name(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
        ];
    }

    /**
     * Get CLI specific information
     */
    private function getCliInfo(): array
    {
        if (!$this->isCli) {
            return [];
        }

        return [
            'argc' => $_SERVER['argc'] ?? 0,
            'argv' => $_SERVER['argv'] ?? [],
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? null,
            'pwd' => getcwd(),
            'user' => get_current_user(),
        ];
    }

    /**
     * Get web request specific information
     */
    private function getWebInfo(): array
    {
        if ($this->isCli) {
            return [];
        }

        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'url' => (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] . '://' : '') .
                ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'unknown') .
                ($_SERVER['REQUEST_URI'] ?? ''),
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'query_string' => $_SERVER['QUERY_STRING'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? null,
            'port' => $_SERVER['SERVER_PORT'] ?? null,
            'host' => $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null,
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'query_params' => $this->sanitizeData($_GET),
        ];
    }

    /**
     * Prepare payload for remote logging
     */
    private function prepareRemotePayload(int $level, string $message, array $context = []): array
    {
        $systemInfo = $this->getSystemInfo();
        $envInfo = [
            'environment' => getenv('APP_ENV') ?: getenv('APPLICATION_ENV') ?: 'production',
            'server_name' => gethostname() ?: ($_SERVER['SERVER_NAME'] ?? 'unknown'),
            'process_id' => getmypid(),
        ];

        $payload = [
            'project_hash' => $this->config->getProjectHash(),
            'timestamp' => (new DateTime())->format(DateTime::ATOM),
            'level' => $level,
            'level_name' => LogLevel::toString($level),
            'message' => $message,
            'context' => $context,
            'system' => $systemInfo,
            'environment' => $envInfo,
        ];

        // Add appropriate information based on execution context
        if ($this->isCli) {
            $payload['cli'] = $this->getCliInfo();
        } else {
            $payload['web'] = $this->getWebInfo();
            $payload['headers'] = $this->getAllHeaders();

            $requestBody = $this->getRequestBody();
            if ($requestBody !== null) {
                $payload['request_body'] = $requestBody;
            }
        }

        // Detect if running in a container
        if (file_exists('/.dockerenv') || (file_exists('/proc/1/cgroup') && strpos(file_get_contents('/proc/1/cgroup'), 'docker') !== false)) {
            $payload['environment']['container'] = true;
        }

        // Add session info if available
        if (!$this->isCli && session_status() === PHP_SESSION_ACTIVE) {
            $payload['session'] = [
                'id' => session_id(),
                'data_size' => $this->formatBytes(strlen(session_encode()))
            ];
        }

        return $payload;
    }

    /**
     * Send log to remote endpoint
     */
    private function sendToRemote(int $level, string $message, array $context = []): void
    {
        if (!$this->config->useRemoteLogging()) {
            return;
        }

        $payload = $this->prepareRemotePayload($level, $message, $context);

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
        if (!function_exists('curl_init') || !extension_loaded('curl')) {
            return false;
        }

        $ch = curl_init($this->config->getRemoteEndpoint());
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Antler-ErrorLogger/1.0',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => $this->config->getRequestTimeout(),
            CURLOPT_CONNECTTIMEOUT => min(5, $this->config->getRequestTimeout()),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_SSL_VERIFYPEER => true,
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
                'header' => "Content-Type: application/json\r\n" .
                    "User-Agent: Antler-ErrorLogger/1.0\r\n" .
                    "Accept: application/json\r\n",
                'content' => $jsonPayload,
                'timeout' => $this->config->getRequestTimeout(),
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        try {
            $response = @file_get_contents($this->config->getRemoteEndpoint(), false, $context);

            // Check for HTTP errors
            if (isset($http_response_header[0])) {
                $parts = explode(' ', $http_response_header[0]);
                if (isset($parts[1]) && $parts[1] >= 400) {
                    $this->writeToErrorLog("Stream send failed: HTTP {$parts[1]}", LogLevel::ERROR);
                    return false;
                }
            }

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

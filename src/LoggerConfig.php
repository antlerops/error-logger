<?php
namespace Antler\ErrorLogger;

class LoggerConfig
{
    private $remoteEndpoint;
    private $projectHash;
    private $requestTimeout;
    private $logFilePath;
    private $minLogLevel;
    private $useRemoteLogging;
    private $useFileLogging;
    private $useErrorLog;
    private $rateLimitPerMinute;
    private $sensitiveKeyPatterns;
    private $reportPHPInfo;
    private $maxRequestBodySize;

    // New configuration properties
    private $samplingRate = 1.0;        // Default: log everything
    private $circuitBreakerThreshold = 0; // Default: disabled
    private $circuitBreakerCooldown = 60; // Default: 60 seconds
    private $filters = []; // Message pattern filters
    private $contextFilters = []; // Context key/value filters
    private $asyncProcessing = false; // Default: disabled

    /**
     * Constructor to initialize the logging configuration.
     *
     * @param array $config Associative array containing configuration options:
     *                      - 'project_hash' (string|null): The project hash for identification. Required for logging.
     *                      - 'remote_endpoint' (string|null): The remote logging endpoint. Required for remote logging.
     *                      - 'request_timeout' (int): Timeout for remote logging requests in seconds. Default: 2.
     *                      - 'log_file_path' (string): Path to the log file. Default: CWD + '/logs/application.log'.
     *                      - 'min_log_level' (int): Minimum log level to handle. Default: LogLevel::WARNING.
     *                      - 'use_remote_logging' (bool): Whether to enable remote logging. Default: true.
     *                      - 'use_file_logging' (bool): Whether to enable logging to a file. Default: true.
     *                      - 'use_error_log' (bool): Whether to log to PHP error log. Default: true.
     *                      - 'rate_limit_per_minute' (int): Number of log messages allowed per minute. Default: 60.
     *                      - 'sensitive_key_patterns' (array): Additional patterns to redact from logs. Default: [].
     *                      - 'report_php_info' (bool): Whether to include detailed PHP configuration. Default: false.
     *                      - 'max_request_body_size' (int): Maximum size in bytes to log for request bodies. Default: 10240.
     *                      - 'sampling_rate' (float): Sampling rate (0.0 to 1.0). Default: 1.0 (log everything)
     *                      - 'circuit_breaker_threshold' (int): Errors per minute to trigger circuit breaker. Default: 0 (disabled)
     *                      - 'circuit_breaker_cooldown' (int): Seconds to keep circuit open. Default: 60
     *                      - 'async_processing' (bool): Whether to use async logging. Default: false
     *
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->projectHash = $this->getConfigValue($config, 'project_hash', 'ANTLER_PROJECT_HASH', null);
        $this->remoteEndpoint = $this->getConfigValue($config, 'remote_endpoint', 'ANTLER_LOG_ENDPOINT', null);
        $this->requestTimeout = (int)$this->getConfigValue($config, 'request_timeout', 'ANTLER_LOG_REQUEST_TIMEOUT', 2);
        $this->logFilePath = $this->getConfigValue($config, 'log_file_path', 'ANTLER_LOG_FILE_PATH', getcwd() . '/logs/application.log');
        $this->minLogLevel = $this->getConfigValue($config, 'min_log_level', null, null);

        if ($this->minLogLevel === null) {
            $this->minLogLevel = $this->getMinLogLevelFromEnv();
        }

        $this->useRemoteLogging = $this->getConfigBool($config, 'use_remote_logging', 'ANTLER_LOG_USE_REMOTE_LOGGING', true);
        $this->useFileLogging = $this->getConfigBool($config, 'use_file_logging', 'ANTLER_LOG_USE_FILE_LOGGING', true);
        $this->useErrorLog = $this->getConfigBool($config, 'use_error_log', 'ANTLER_LOG_USE_ERROR_LOG', true);
        $this->rateLimitPerMinute = (int)$this->getConfigValue($config, 'rate_limit_per_minute', 'ANTLER_LOG_RATE_LIMIT_PER_MINUTE', 60);

        // Original additional configuration options
        $this->sensitiveKeyPatterns = $config['sensitive_key_patterns'] ?? [];
        $this->reportPHPInfo = $this->getConfigBool($config, 'report_php_info', 'ANTLER_LOG_REPORT_PHP_INFO', false);
        $this->maxRequestBodySize = (int)$this->getConfigValue($config, 'max_request_body_size', 'ANTLER_LOG_MAX_REQUEST_BODY_SIZE', 10240);

        // New configuration options
        $this->samplingRate = (float)$this->getConfigValue($config, 'sampling_rate', 'ANTLER_LOG_SAMPLING_RATE', 1.0);
        $this->circuitBreakerThreshold = (int)$this->getConfigValue($config, 'circuit_breaker_threshold', 'ANTLER_LOG_CIRCUIT_BREAKER_THRESHOLD', 0);
        $this->circuitBreakerCooldown = (int)$this->getConfigValue($config, 'circuit_breaker_cooldown', 'ANTLER_LOG_CIRCUIT_BREAKER_COOLDOWN', 60);
        $this->asyncProcessing = $this->getConfigBool($config, 'async_processing', 'ANTLER_LOG_ASYNC_PROCESSING', false);
    }

    /**
     * Get a configuration value from array, environment, or default
     *
     * @param array $config The configuration array
     * @param string $key The configuration key to look for
     * @param string|null $envVar The environment variable name to check if key not in config
     * @param mixed $default The default value if not found in config or env
     * @return mixed The config value
     */
    private function getConfigValue(array $config, string $key, ?string $envVar, $default)
    {
        if (isset($config[$key])) {
            return $config[$key];
        }

        if ($envVar !== null) {
            $envValue = getenv($envVar);
            if ($envValue !== false) {
                return $envValue;
            }
        }

        return $default;
    }

    /**
     * Get a boolean configuration value from array, environment, or default
     *
     * @param array $config The configuration array
     * @param string $key The configuration key to look for
     * @param string $envVar The environment variable name to check if key not in config
     * @param bool $default The default value if not found in config or env
     * @return bool The config value
     */
    private function getConfigBool(array $config, string $key, string $envVar, bool $default): bool
    {
        if (isset($config[$key])) {
            return (bool)$config[$key];
        }

        $envValue = getenv($envVar);
        if ($envValue !== false) {
            return filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        }

        return $default;
    }

    /**
     * Helper method to parse min log level from environment variable.
     */
    private function getMinLogLevelFromEnv(): int
    {
        $envLogLevel = getenv('ANTLER_LOG_MIN_LOG_LEVEL');
        if ($envLogLevel === false) {
            return LogLevel::WARNING;
        }

        // Try to map string log level to constant
        $levelMap = [
            'DEBUG' => LogLevel::DEBUG,
            'INFO' => LogLevel::INFO,
            'WARNING' => LogLevel::WARNING,
            'WARN' => LogLevel::WARNING,
            'ERROR' => LogLevel::ERROR,
            'CRITICAL' => LogLevel::CRITICAL,
            'FATAL' => LogLevel::CRITICAL
        ];

        $upperLevel = strtoupper($envLogLevel);
        if (isset($levelMap[$upperLevel])) {
            return $levelMap[$upperLevel];
        }

        // If numeric value provided, use it directly
        if (is_numeric($envLogLevel)) {
            return (int)$envLogLevel;
        }

        // Try class constant as fallback
        $constantName = __NAMESPACE__ . '\\LogLevel::' . $upperLevel;
        return defined($constantName) ? constant($constantName) : LogLevel::WARNING;
    }

    /**
     * Get the remote endpoint URL.
     *
     * @return string|null The remote endpoint URL or null if not set.
     */
    public function getRemoteEndpoint(): ?string
    {
        return $this->remoteEndpoint;
    }

    /**
     * Get the project hash value.
     *
     * @return string|null The project hash or null if not set.
     */
    public function getProjectHash(): ?string
    {
        return $this->projectHash;
    }

    /**
     * Get the request timeout value.
     *
     * @return int The timeout duration for remote requests in seconds.
     */
    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    /**
     * Get the log file path.
     *
     * @return string The file path for local logging.
     */
    public function getLogFilePath(): string
    {
        return $this->logFilePath;
    }

    /**
     * Get the minimum log level.
     *
     * @return int The minimum log level to process.
     */
    public function getMinLogLevel(): int
    {
        return $this->minLogLevel;
    }

    /**
     * Check if remote logging is enabled.
     *
     * @return bool True if remote logging is enabled, false otherwise.
     */
    public function useRemoteLogging(): bool
    {
        return $this->useRemoteLogging;
    }

    /**
     * Check if file logging is enabled.
     *
     * @return bool True if file logging is enabled, false otherwise.
     */
    public function useFileLogging(): bool
    {
        return $this->useFileLogging;
    }

    /**
     * Check if PHP error log is enabled.
     *
     * @return bool True if PHP error log is enabled, false otherwise.
     */
    public function useErrorLog(): bool
    {
        return $this->useErrorLog;
    }

    /**
     * Get the rate limit per minute.
     *
     * @return int The maximum number of log entries per minute.
     */
    public function getRateLimitPerMinute(): int
    {
        return $this->rateLimitPerMinute;
    }

    /**
     * Get the sensitive key patterns to redact.
     *
     * @return array List of patterns to redact in addition to built-in ones.
     */
    public function getSensitiveKeyPatterns(): array
    {
        return $this->sensitiveKeyPatterns;
    }

    /**
     * Whether to include detailed PHP configuration in logs.
     *
     * @return bool True if detailed PHP info should be included, false otherwise.
     */
    public function shouldReportPHPInfo(): bool
    {
        return $this->reportPHPInfo;
    }

    /**
     * Get maximum size to log for request bodies in bytes.
     *
     * @return int Maximum size in bytes for request body logging.
     */
    public function getMaxRequestBodySize(): int
    {
        return $this->maxRequestBodySize;
    }

    // --- NEW METHODS ---

    /**
     * Get the sampling rate.
     *
     * @return float Sampling rate (0.0 to 1.0)
     */
    public function getSamplingRate(): float
    {
        return $this->samplingRate;
    }

    /**
     * Set log sampling rate (0.0 to 1.0)
     *
     * @param float $rate Sampling rate (0.0 = log nothing, 1.0 = log everything)
     * @return self
     */
    public function setSamplingRate(float $rate): self
    {
        $this->samplingRate = max(0.0, min(1.0, $rate));
        return $this;
    }

    /**
     * Get circuit breaker threshold.
     *
     * @return int Number of errors per minute to trigger circuit breaker (0 = disabled)
     */
    public function getCircuitBreakerThreshold(): int
    {
        return $this->circuitBreakerThreshold;
    }

    /**
     * Set circuit breaker threshold (0 to disable)
     *
     * @param int $threshold Number of errors per minute to trigger circuit breaker
     * @return self
     */
    public function setCircuitBreakerThreshold(int $threshold): self
    {
        $this->circuitBreakerThreshold = max(0, $threshold);
        return $this;
    }

    /**
     * Get circuit breaker cooldown period in seconds.
     *
     * @return int Cooldown period in seconds
     */
    public function getCircuitBreakerCooldown(): int
    {
        return $this->circuitBreakerCooldown;
    }

    /**
     * Set circuit breaker cooldown period
     *
     * @param int $seconds Cooldown period in seconds
     * @return self
     */
    public function setCircuitBreakerCooldown(int $seconds): self
    {
        $this->circuitBreakerCooldown = max(1, $seconds);
        return $this;
    }

    /**
     * Add a message pattern filter
     *
     * @param string $pattern Regex pattern to filter log messages
     * @return self
     */
    public function addMessageFilter(string $pattern): self
    {
        $this->filters[] = $pattern;
        return $this;
    }

    /**
     * Add a context key/value filter
     *
     * @param string $key Context key to check
     * @param mixed $value Value or regex pattern to match
     * @param bool $isRegex Whether $value is a regex pattern
     * @return self
     */
    public function addContextFilter(string $key, $value, bool $isRegex = false): self
    {
        $this->contextFilters[] = [
            'key' => $key,
            'value' => $value,
            'is_regex' => $isRegex
        ];
        return $this;
    }

    /**
     * Get message filters
     *
     * @return array List of message filter patterns
     */
    public function getMessageFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get context filters
     *
     * @return array List of context filters
     */
    public function getContextFilters(): array
    {
        return $this->contextFilters;
    }

    /**
     * Check if async processing is enabled
     *
     * @return bool
     */
    public function useAsyncProcessing(): bool
    {
        return $this->asyncProcessing;
    }

    /**
     * Enable or disable asynchronous processing
     *
     * @param bool $enabled Whether to enable async processing
     * @return self
     */
    public function setAsyncProcessing(bool $enabled): self
    {
        $this->asyncProcessing = $enabled;
        return $this;
    }
}

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

    /**
     * Constructor to initialize the logging configuration.
     *
     * @param array $config Associative array containing the configuration options:
     *                      - 'log_file_path' (string): The path to the log file. Defaults to the current working directory with `/logs/application.log`.
     *                      - 'remote_endpoint' (string|null): The remote logging endpoint. Defaults to the value of `ANTLER_LOG_ENDPOINT` environment variable or null.
     *                      - 'project_hash' (string|null): The project hash for identification. Defaults to the value of `PROJECT_HASH` environment variable or null.
     *                      - 'request_timeout' (int): The timeout for remote logging requests in seconds. Defaults to 2.
     *                      - 'min_log_level' (string): The minimum log level to handle. Defaults to `LogLevel::WARNING`.
     *                      - 'use_remote_logging' (bool): Whether to enable remote logging. Defaults to true.
     *                      - 'use_file_logging' (bool): Whether to enable logging to a file. Defaults to true.
     *                      - 'use_error_log' (bool): Whether to log to PHP error log. Defaults to true.
     *                      - 'rate_limit_per_minute' (int): Number of log messages allowed per minute. Defaults to 60.
     *
     * @return void
     */
    public function __construct(array $config = [])
    {

        $this->logFilePath = isset($config['log_file_path']) ? $config['log_file_path'] : (getenv('ANTLER_LOG_FILE_PATH') ?: getcwd() . '/logs/application.log');
        $this->remoteEndpoint = isset($config['remote_endpoint']) ? $config['remote_endpoint'] : (getenv('ANTLER_LOG_ENDPOINT') ?: null);
        $this->projectHash = isset($config['project_hash']) ? $config['project_hash'] : (getenv('ANTLER_PROJECT_HASH') ?: null);
        $this->requestTimeout = isset($config['request_timeout']) ? $config['request_timeout'] : (int)(getenv('ANTLER_LOG_REQUEST_TIMEOUT') ?: 2);
        $this->minLogLevel = isset($config['min_log_level']) ? $config['min_log_level'] : $this->getMinLogLevelFromEnv();
        $this->useRemoteLogging = isset($config['use_remote_logging']) ? $config['use_remote_logging'] : $this->getEnvBool('ANTLER_LOG_USE_REMOTE_LOGGING', true);
        $this->useFileLogging = isset($config['use_file_logging']) ? $config['use_file_logging'] : $this->getEnvBool('ANTLER_LOG_USE_FILE_LOGGING', true);
        $this->useErrorLog = isset($config['use_error_log']) ? $config['use_error_log'] : $this->getEnvBool('ANTLER_LOG_USE_ERROR_LOG', true);
        $this->rateLimitPerMinute = isset($config['rate_limit_per_minute']) ? $config['rate_limit_per_minute'] : (int)(getenv('ANTLER_LOG_RATE_LIMIT_PER_MINUTE') ?: 60);
    }

    /**
     * Helper method to parse a boolean from an environment variable.
     */
    private function getEnvBool(string $envVar, bool $default): bool
    {
        $value = getenv($envVar);
        return $value !== false ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : $default;
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
        $constantName = __NAMESPACE__ . '\\LogLevel::' . strtoupper($envLogLevel);
        return defined($constantName) ? constant($constantName) : LogLevel::WARNING;
    }

    /**
     * Retrieves the remote endpoint URL as a string.
     *
     * @return string The remote endpoint URL.
     */
    public function getRemoteEndpoint(): ?string
    {
        return $this->remoteEndpoint;
    }

    /**
     * Retrieves the hash value of the project.
     *
     * @return string|null The hash of the project or null if not set.
     */
    public function getProjectHash(): ?string
    {
        return $this->projectHash;
    }

    /**
     * Retrieves the request timeout value.
     *
     * @return int The timeout duration for the request.
     */
    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    /**
     * Retrieves the path of the log file.
     *
     * @return string The file path of the log file.
     */
    public function getLogFilePath(): string
    {
        return $this->logFilePath;
    }

    /**
     * Retrieves the minimum log level.
     *
     * @return int The minimum log level.
     */
    public function getMinLogLevel(): int
    {
        return $this->minLogLevel;
    }

    /**
     * Determines if remote logging is enabled.
     *
     * @return bool True if remote logging is enabled, false otherwise.
     */
    public function useRemoteLogging(): bool
    {
        return $this->useRemoteLogging;
    }

    /**
     * Indicates if file logging is enabled.
     *
     * @return bool True if file logging is enabled, false otherwise.
     */
    public function useFileLogging(): bool
    {
        return $this->useFileLogging;
    }

    /**
     * Indicates whether the error log is enabled.
     *
     * @return bool True if the error log is enabled, false otherwise.
     */
    public function useErrorLog(): bool
    {
        return $this->useErrorLog;
    }

    /**
     * Retrieves the rate limit per minute.
     *
     * @return int The rate limit per minute.
     */
    public function getRateLimitPerMinute(): int
    {
        return $this->rateLimitPerMinute;
    }
}

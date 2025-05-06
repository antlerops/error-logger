# Antler Error Logger

An advanced PHP error logging system with comprehensive context capture, multiple transport options, and robust error handling for PHP 7.1+ and PHP 8.0+ applications.

## Features

- **Multi-Transport Logging**: Log to remote endpoints, local files, and PHP error log simultaneously
- **Intelligent Environment Detection**: Automatically captures different context in CLI vs. web environments
- **Rich Context**: Captures system state, execution performance, HTTP request details, and more
- **Privacy-Aware**: Automatically sanitizes sensitive data (passwords, tokens, etc.)
- **Rate Limiting**: Prevents log flooding during high-volume error situations
- **Performance Monitoring**: Tracks memory usage and execution time for errors
- **Simple Integration**: Drop-in error handling with minimal configuration
- **Container-Aware**: Detects containerized environments

## Installation

```bash
composer require antlerops/error-logger
```

## Basic Usage

```php
use Antler\ErrorLogger\Logger;
use Antler\ErrorLogger\LoggerConfig;
use Antler\ErrorLogger\LogLevel;

// Quick setup with minimal configuration
$config = new LoggerConfig([
    'project_hash' => 'your-project-identifier',
    'remote_endpoint' => 'https://logs.yourservice.com/api'
]);

$logger = Logger::getInstance($config);

// Log examples
$logger->debug('SQL query executed', ['query' => $sql, 'duration' => $queryTime]);
$logger->info('User logged in', ['user_id' => $userId]);
$logger->warning('Deprecated feature used', ['feature' => 'oldApi']);
$logger->error('Payment failed', ['order_id' => $orderId, 'amount' => $amount]);
$logger->critical('System is out of disk space');

// Exceptions are automatically captured by the global handler
// but you can also log caught exceptions with full context
try {
    // Your code
} catch (Exception $e) {
    $logger->error('Caught exception', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
```

## Configuration Options

| Config Key              | Default Value           | Description                                               |
|-------------------------|-------------------------|-----------------------------------------------------------|
| `project_hash`          | `null`                  | **Required**: Unique project identifier                   |
| `remote_endpoint`       | `null`                  | Remote logging API URL (Required for remote logging)      |
| `log_file_path`         | `./logs/application.log`| Path to local log file                                    |
| `request_timeout`       | `2`                     | Timeout in seconds for remote requests                    |
| `min_log_level`         | `LogLevel::WARNING`     | Minimum severity level to log (DEBUG to CRITICAL)         |
| `use_remote_logging`    | `true`                  | Enable/disable remote logging                             |
| `use_file_logging`      | `true`                  | Enable/disable local file logging                         |
| `use_error_log`         | `true`                  | Use PHP error_log() for ERROR+ levels                     |
| `rate_limit_per_minute` | `60`                    | Maximum allowed logs per minute to prevent flooding       |

## Environment Variables

All configuration options can be set via environment variables:

```ini
ANTLER_PROJECT_HASH="your-project-hash"
ANTLER_LOG_ENDPOINT="https://logs.yourservice.com/api"
ANTLER_LOG_FILE_PATH="/var/log/app.log"
ANTLER_LOG_REQUEST_TIMEOUT="3"
ANTLER_LOG_MIN_LOG_LEVEL="DEBUG"  # DEBUG, INFO, WARNING, ERROR, CRITICAL
ANTLER_LOG_USE_REMOTE_LOGGING="true"
ANTLER_LOG_USE_FILE_LOGGING="true"
ANTLER_LOG_USE_ERROR_LOG="true"
ANTLER_LOG_RATE_LIMIT_PER_MINUTE="100"
```

Configuration in code overrides environment variables.

## Log Levels

The logger offers five standard severity levels:

```php
LogLevel::DEBUG    // 100 - Detailed information for debugging
LogLevel::INFO     // 200 - Interesting events in your application
LogLevel::WARNING  // 300 - Non-error but potentially problematic situations
LogLevel::ERROR    // 400 - Runtime errors that don't require immediate action
LogLevel::CRITICAL // 500 - Critical errors requiring immediate intervention
```

## Automatic Context Capture

The logger automatically captures different information depending on the execution environment:

### Common to Both CLI and Web

- Project identifier
- PHP version and SAPI
- Memory usage, peak, and growth since logger initialization
- Memory limit
- Execution time
- Maximum execution time
- Operating system
- Timezone
- Server hostname
- Process ID
- Container detection

### CLI-Specific Context

- Script arguments (argc/argv)
- Script filename
- Current working directory
- Current user

### Web-Specific Context

- Full URL and components (scheme, host, path)
- HTTP method
- Query parameters (sanitized)
- Client IP address
- User agent
- Referrer
- HTTP headers
- Request body (JSON or form data, sanitized)
- Session information (when available)

## Data Privacy and Sanitization

The logger automatically redacts sensitive information from request data. Fields containing the following strings will be redacted:

- password, passwd
- secret, token, auth
- key, apikey, api_key
- access_token, accesstoken
- credential, private
- ssn, social_security
- cc, card, credit, cvv, cvc

For example:
```json
{
  "user": "johndoe",
  "api_key": "[REDACTED]",
  "password": "[REDACTED]",
  "metadata": {
    "access_token": "[REDACTED]"
  }
}
```

## Advanced Configuration Examples

### Disable Remote Logging in Development

```php
// For local development, keep file logging but disable remote
$logger = Logger::getInstance(new LoggerConfig([
    'project_hash' => 'dev-project',
    'use_remote_logging' => false,
    'log_file_path' => __DIR__ . '/logs/dev.log',
    'min_log_level' => LogLevel::DEBUG
]));
```

### High-Traffic Production Environment

```php
// For high-traffic production, focus on performance
$logger = Logger::getInstance(new LoggerConfig([
    'project_hash' => 'prod-project',
    'remote_endpoint' => 'https://logs.yourservice.com/api',
    'min_log_level' => LogLevel::ERROR,
    'use_file_logging' => false,       // Skip file logging for performance
    'rate_limit_per_minute' => 200,    // Higher rate limit
    'request_timeout' => 1             // Lower timeout to prevent blocking
]));
```

### CLI Application Example

```php
// For CLI applications, customize log path by command
$logger = Logger::getInstance(new LoggerConfig([
    'project_hash' => 'cli-app',
    'log_file_path' => __DIR__ . '/logs/' . basename($argv[0]) . '.log',
    'use_remote_logging' => true,
    'min_log_level' => LogLevel::INFO
]));
```

## Remote Payload Example

Here's an example of what the logger sends to remote endpoints:

```json
{
  "project_hash": "example-project",
  "timestamp": "2023-09-15T14:23:01+00:00",
  "level": 400,
  "level_name": "ERROR",
  "message": "Database connection failed",
  "context": {
    "host": "db.example.com",
    "error_code": 1045
  },
  "system": {
    "php_version": "8.1.12",
    "os": "Linux",
    "memory_usage": "14.2 MB",
    "memory_peak": "16.5 MB",
    "memory_growth": "7.5 MB",
    "memory_limit": "128M",
    "execution_time": "0.1234s",
    "max_execution_time": "30",
    "timezone": "UTC",
    "sapi": "fpm-fcgi",
    "server_software": "nginx/1.20.1"
  },
  "environment": {
    "environment": "production",
    "server_name": "web-prod-03",
    "process_id": 12345,
    "container": true
  },
  "web": {
    "method": "POST",
    "url": "https://api.example.com/users",
    "path": "/users",
    "query_string": "source=signup",
    "ip": "203.0.113.42",
    "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "referer": "https://example.com/signup",
    "protocol": "HTTP/1.1",
    "port": "443",
    "host": "api.example.com",
    "https": true,
    "query_params": {
      "source": "signup"
    }
  },
  "headers": {
    "Accept": "application/json",
    "Content-Type": "application/json",
    "Authorization": "[REDACTED]"
  },
  "request_body": {
    "name": "John Doe",
    "email": "john@example.com",
    "password": "[REDACTED]"
  },
  "session": {
    "id": "abc123def456",
    "data_size": "2.3 KB"
  }
}
```

## Troubleshooting

### Remote Logging Not Working

1. Check connectivity to your remote endpoint:
   ```php
   $url = $logger->getConfig()->getRemoteEndpoint();
   echo "Can connect: " . (file_get_contents($url, false, 
     stream_context_create(['http' => ['method' => 'HEAD']])) ? 'Yes' : 'No');
   ```

2. Verify your PHP installation has required extensions:
   ```php
   echo "cURL available: " . (extension_loaded('curl') ? 'Yes' : 'No') . "\n";
   echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'Yes' : 'No');
   ```

3. Check for PHP warnings in your error log about cURL or stream requests.

### File Logging Issues

1. Check directory permissions:
   ```php
   $logDir = dirname($logger->getConfig()->getLogFilePath());
   echo "Directory exists: " . (is_dir($logDir) ? 'Yes' : 'No') . "\n";
   echo "Directory is writable: " . (is_writable($logDir) ? 'Yes' : 'No');
   ```

2. Create the log directory manually and set proper permissions:
   ```bash
   mkdir -p /path/to/logs
   chmod 755 /path/to/logs
   ```

## PHP Error Handler Integration

The logger automatically registers handlers for:

- Uncaught exceptions (via `set_exception_handler`)
- PHP errors (via `set_error_handler`)
- Fatal errors (via `register_shutdown_function`)

These handlers capture detailed information about errors and send them through all configured transports.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Requirements

- PHP 7.1 or higher
- For remote logging: Either cURL extension or `allow_url_fopen` enabled

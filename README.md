# Antler Error Logger

Advanced PHP error logging system with multiple transports, rate limiting, and context support. Compatible with PHP 7.1+ and PHP 8.0+.

## Installation

```bash
composer require antlerops/error-logger
```

## Basic Usage

```php
use Antler\ErrorLogger\Logger;
use Antler\ErrorLogger\LoggerConfig;
use Antler\ErrorLogger\LogLevel;

// Minimal configuration
$config = [
    'project_hash' => 'your-project-identifier',
    'remote_endpoint' => 'https://logs.yourservice.com/api',
];

$logger = Logger::getInstance(new LoggerConfig($config));

// Log examples
$logger->debug('Debugging data', ['query' => $sql]);
$logger->error('Payment failed', ['user_id' => 42, 'amount' => 100]);
```

## Full Configuration Options

| Config Key              | Default Value                     | Description                                                                 |
|-------------------------|-----------------------------------|-----------------------------------------------------------------------------|
| `remote_endpoint`       | `null`                           | Remote logging API URL (Required for remote logging)                       |
| `project_hash`          | `null`                           | Project identifier (Required)                                              |
| `request_timeout`       | `2`                              | Timeout in seconds for remote requests                                     |
| `log_file_path`         | `./logs/application.log`         | Absolute path to local log file                                            |
| `min_log_level`         | `LogLevel::WARNING`              | Minimum severity level to log (DEBUG=100, CRITICAL=500)                    |
| `use_remote_logging`    | `true`                           | Enable/disable remote logging                                              |
| `use_file_logging`      | `true`                           | Enable/disable local file logging                                          |
| `use_error_log`         | `true`                           | Fallback to PHP error_log() for ERROR+ levels                              |
| `rate_limit_per_minute` | `60`                             | Maximum allowed logs per minute to prevent flooding                        |

## Environment Variables

All configuration options can be set via environment variables (values in code take precedence):

- `ANTLER_LOG_ENDPOINT`: Remote logging API URL
- `ANTLER_PROJECT_HASH`: Project identifier
- `ANTLER_LOG_FILE_PATH`: Path to local log file
- `ANTLER_LOG_REQUEST_TIMEOUT`: Remote request timeout in seconds (integer)
- `ANTLER_LOG_MIN_LOG_LEVEL`: Minimum severity (DEBUG, INFO, WARNING, ERROR, CRITICAL)
- `ANTLER_LOG_USE_REMOTE_LOGGING`: Enable remote logging (true/false)
- `ANTLER_LOG_USE_FILE_LOGGING`: Enable file logging (true/false)
- `ANTLER_LOG_USE_ERROR_LOG`: Enable PHP error_log fallback (true/false)
- `ANTLER_LOG_RATE_LIMIT_PER_MINUTE`: Max logs per minute (integer)

## Configuration Examples

### Environment Variables Example (.env)
```ini
ANTLER_PROJECT_HASH="proj_1234"
ANTLER_LOG_ENDPOINT="https://logs.yourservice.com"
ANTLER_LOG_MIN_LOG_LEVEL="DEBUG"
ANTLER_LOG_FILE_PATH="/var/logs/app.log"
ANTLER_LOG_USE_FILE_LOGGING="true"
```

PHP code:
```php
// Empty config will use environment variables
$logger = Logger::getInstance(new LoggerConfig());
```

### Hardcoded Configuration Example
```php
$config = [
    'project_hash' => 'proj_1234',
    'remote_endpoint' => 'https://logs.yourservice.com',
    'min_log_level' => LogLevel::DEBUG,
    'log_file_path' => '/var/logs/app.log',
    'use_file_logging' => true,
    'rate_limit_per_minute' => 100
];

$logger = Logger::getInstance(new LoggerConfig($config));
```

### Mixed Configuration Example
.env:
```ini
ANTLER_PROJECT_HASH="proj_1234"
ANTLER_LOG_REQUEST_TIMEOUT="5"
```

PHP code:
```php
$config = [
    'remote_endpoint' => 'https://logs.yourservice.com',  // Overrides env if present
    'use_error_log' => false
];

// Uses: 
// - remote_endpoint from array
// - project_hash from env
// - request_timeout from env
// - use_error_log from array
$logger = Logger::getInstance(new LoggerConfig($config));
```

## Error Levels

Use these constants from the `LogLevel` class:

```php
LogLevel::DEBUG    // 100
LogLevel::INFO     // 200
LogLevel::WARNING  // 300
LogLevel::ERROR    // 400
LogLevel::CRITICAL // 500
```

## Advanced Setup

### Custom Configuration

```php
$config = [
    'use_file_logging' => false, // Disable file logs
    'request_timeout' => 5,
    'rate_limit_per_minute' => 100,
    'min_log_level' => LogLevel::DEBUG,
];

$logger = Logger::getInstance(new LoggerConfig($config));
```

### Handling Exceptions

All exceptions are automatically captured and logged as CRITICAL:
```php
try {
    // Risky operation
} catch (Exception $e) {
    // Already logged automatically!
}
```

## Troubleshooting File Logging

If you're experiencing issues with local file logging:

1. **Check directory permissions**:
   ```php
   $logDir = dirname($logger->getConfig()->getLogFilePath());
   echo "Directory exists: " . (is_dir($logDir) ? 'Yes' : 'No') . "\n";
   echo "Directory is writable: " . (is_writable($logDir) ? 'Yes' : 'No') . "\n";
   ```

2. **Validate file path**:
   Make sure the log file path is absolute or relative to the correct working directory.

3. **Test with explicit path**:
   ```php
   $config = [
       'project_hash' => 'test-project',
       'log_file_path' => __DIR__ . '/logs/application.log',
       'use_remote_logging' => false
   ];
   ```

4. **Check PHP error log**:
   If file logging fails, errors will be written to PHP's error_log if `use_error_log` is enabled.

## Log Format Example

Local file entries include structured context:
```
[2023-09-15T14:23:01+00:00] [ERROR] Payment failed {"user_id":42,"amount":100}
```

Remote payloads include additional metadata:
```json
{
    "project_hash": "your-project-identifier",
    "level": 400,
    "level_name": "ERROR",
    "message": "Payment failed",
    "context": {"user_id":42,"amount":100},
    "timestamp": "2023-09-15T14:23:01+00:00",
    "method": "GET",
    "server_name": "prod-web-01",
    "url": "https://about:blank",
    "ip": "203.0.113.42",
    "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) ...",
    "environment": "production",
    "headers": [],
    "query_params": [],
    "request_body": []
}
```

## Testing the Logger

A simple test script to verify your logger is working:

```php
<?php
require_once 'vendor/autoload.php';

use Antler\ErrorLogger\Logger;
use Antler\ErrorLogger\LoggerConfig;
use Antler\ErrorLogger\LogLevel;

$config = [
    'project_hash' => 'test-project-hash',
    'log_file_path' => __DIR__ . '/logs/test.log',
    'min_log_level' => LogLevel::DEBUG,
    'use_remote_logging' => false,
    'use_file_logging' => true
];

$logger = Logger::getInstance(new LoggerConfig($config));
$logger->debug('Test message');

if (file_exists(__DIR__ . '/logs/test.log')) {
    echo "Success: Log file created!";
} else {
    echo "Error: Log file not created.";
}
```

## Requirements

- PHP 7.1 or higher
- Either cURL extension or `allow_url_fopen` enabled for remote logging

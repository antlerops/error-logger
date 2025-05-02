# Antler Error Logger

Advanced PHP error logging system with multiple transports, rate limiting, and context support.

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

These can be used instead of hardcoding values in configuration:

- `ANTLER_LOG_ENDPOINT`: Sets `remote_endpoint`
- `PROJECT_HASH`: Sets `project_hash`

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
    "message": "Payment failed",
    "context": {"user_id":42,"amount":100},
    "timestamp": "2023-09-15T14:23:01+00:00",
    "server_name": "prod-web-01",
    "ip": "203.0.113.42",
    "environment": "production"
}
```

## Requirements

- PHP 8.0 or higher
- Either cURL extension or `allow_url_fopen` enabled for remote logging

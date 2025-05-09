# Antler Error Logger

An advanced PHP error logging system with comprehensive context capture, multiple transport options, and robust error handling for PHP 7.1+ and PHP 8.0+ applications.

## Features

- **Multi-Transport Logging**: Log to remote endpoints, local files, and PHP error log simultaneously
- **Enhanced Stack Traces**: Captures source code context around errors with line numbers
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
    'remote_endpoint' => 'LOCAL_DOMAIN/api/log/rec'
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
ANTLER_LOG_ENDPOINT="LOCAL_DOMAIN/api/log/rec"
ANTLER_LOG_FILE_PATH="/var/log/app.log"
ANTLER_LOG_REQUEST_TIMEOUT="3"
ANTLER_LOG_MIN_LOG_LEVEL="DEBUG"  # DEBUG, INFO, WARNING, ERROR, CRITICAL
ANTLER_LOG_USE_REMOTE_LOGGING="true"
ANTLER_LOG_USE_FILE_LOGGING="true"
ANTLER_LOG_USE_ERROR_LOG="true"
ANTLER_LOG_RATE_LIMIT_PER_MINUTE="100"
```

Configuration in code overrides environment variables.

## Enhanced Stack Traces with Code Context

One of the powerful features of Antler Error Logger is the ability to capture detailed code context around errors. This makes debugging easier by showing you exactly what was happening in your code when the error occurred:

```php
try {
    throw new Exception("Something went wrong");
} catch (Exception $e) {
    $logger->error("Caught exception", ["exception" => $e]);
}
```

This will include the enhanced stack trace with source code context in your logs.

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

## Remote Payload Example

Here's an example of what the logger sends to remote endpoints, including the enhanced stack trace:

```json
{
  "project_hash": "example-project",
  "timestamp": "2023-09-15T14:23:01+00:00",
  "level": 400,
  "level_name": "ERROR",
  "message": "Database connection failed",
  "context": {
    "exception_class": "PDOException",
    "file": "/var/www/app/src/Database.php",
    "line": 45,
    "code": 1045,
    "enhanced_trace": {
      "frames": [
        {
          "file": "/var/www/app/src/Database.php",
          "line": 45,
          "function": null,
          "class": null,
          "type": null,
          "args": [],
          "code_context": {
            "file": "/var/www/app/src/Database.php",
            "line": 45,
            "start_line": 40,
            "end_line": 50,
            "context": {
              "40": {
                "content": "    public function connect() {",
                "is_error_line": false
              },
              "41": {
                "content": "        try {",
                "is_error_line": false
              },
              "42": {
                "content": "            $dsn = sprintf(",
                "is_error_line": false
              },
              "43": {
                "content": "                'mysql:host=%s;dbname=%s;charset=utf8mb4',",
                "is_error_line": false
              },
              "44": {
                "content": "                $this->config['host'], $this->config['database']",
                "is_error_line": false
              },
              "45": {
                "content": "            $this->pdo = new PDO($dsn, $this->config['user'], $this->config['password']);",
                "is_error_line": true
              },
              "46": {
                "content": "            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);",
                "is_error_line": false
              },
              "47": {
                "content": "            return true;",
                "is_error_line": false
              },
              "48": {
                "content": "        } catch (PDOException $e) {",
                "is_error_line": false
              },
              "49": {
                "content": "            throw $e;",
                "is_error_line": false
              },
              "50": {
                "content": "        }",
                "is_error_line": false
              }
            }
          }
        },
        {
          "file": "/var/www/app/src/App.php",
          "line": 28,
          "function": "connect",
          "class": "Database",
          "type": "->",
          "args": [],
          "code_context": {
            "file": "/var/www/app/src/App.php",
            "line": 28,
            "start_line": 23,
            "end_line": 33,
            "context": {
              "23": {
                "content": "    public function initialize() {",
                "is_error_line": false
              },
              "24": {
                "content": "        // Load configuration",
                "is_error_line": false
              },
              "25": {
                "content": "        $this->config = require __DIR__ . '/../config/config.php';",
                "is_error_line": false
              },
              "26": {
                "content": "",
                "is_error_line": false
              },
              "27": {
                "content": "        // Initialize database",
                "is_error_line": false
              },
              "28": {
                "content": "        $this->db->connect();",
                "is_error_line": true
              },
              "29": {
                "content": "",
                "is_error_line": false
              },
              "30": {
                "content": "        // Set up routes",
                "is_error_line": false
              },
              "31": {
                "content": "        $this->setupRoutes();",
                "is_error_line": false
              },
              "32": {
                "content": "    }",
                "is_error_line": false
              },
              "33": {
                "content": "",
                "is_error_line": false
              }
            }
          }
        }
      ],
      "exception_class": "PDOException",
      "message": "SQLSTATE[HY000] [1045] Access denied for user 'webapp'@'172.18.0.3' (using password: YES)",
      "code": 1045
    },
    "trace": "PDOException: SQLSTATE[HY000] [1045]... (truncated for brevity)"
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

## Advanced Configuration Examples

### Development Environment

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
    'remote_endpoint' => 'LOCAL_DOMAIN/api/log/rec',
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

## Laravel Integration

Antler Error Logger integrates seamlessly with Laravel applications, providing enhanced error logging and monitoring for your Laravel projects.

### Supported Laravel Versions

Antler Error Logger is compatible with the following Laravel versions:

| Laravel Version | PHP Compatibility | Support Status |
|-----------------|-------------------|---------------|
| Laravel 6.x     | PHP 7.2 or higher | ✓ Supported   |
| Laravel 7.x     | PHP 7.2.5 or higher | ✓ Supported |
| Laravel 8.x     | PHP 7.3 or higher | ✓ Supported   |
| Laravel 9.x     | PHP 8.0.2 or higher | ✓ Supported |
| Laravel 10.x    | PHP 8.1 or higher | ✓ Supported   |
| Laravel 11.x    | PHP 8.2 or higher | ✓ Supported   |

> **Note**: For PHP 7.1 compatibility, you must use Laravel 5.8 or earlier. Laravel 6.0+ requires PHP 7.2+.

### Installation in Laravel

1. Install the package via Composer:

```bash
composer require antlerops/error-logger
```

2. Publish the configuration file:

```bash
php artisan vendor:publish --provider="Antler\ErrorLogger\LaravelServiceProvider"
```

This will create a `config/antler-error-logger.php` configuration file in your application.

### Laravel Service Provider

When using Laravel, the package will automatically register its service provider if you're using Laravel's package auto-discovery. If auto-discovery is disabled, add the service provider manually to your `config/app.php` file:

```php
'providers' => [
    // ...
    Antler\ErrorLogger\LaravelServiceProvider::class,
],
```

### Configuration in Laravel

After publishing the configuration, you can modify the settings in the `config/antler-error-logger.php` file. The Laravel integration inherits all options from the standard configuration, with some Laravel-specific additions:

```php
return [
    // Standard ErrorLogger options
    'project_hash' => env('ANTLER_PROJECT_HASH', null),
    'remote_endpoint' => env('ANTLER_LOG_ENDPOINT', null),
    'use_remote_logging' => env('ANTLER_LOG_USE_REMOTE_LOGGING', true),
    'use_file_logging' => env('ANTLER_LOG_USE_FILE_LOGGING', true),
    'use_error_log' => env('ANTLER_LOG_USE_ERROR_LOG', true),
    'log_file_path' => env('ANTLER_LOG_FILE_PATH', storage_path('logs/antler.log')),
    'min_log_level' => env('ANTLER_LOG_MIN_LOG_LEVEL', \Antler\ErrorLogger\LogLevel::WARNING),
    'rate_limit_per_minute' => env('ANTLER_LOG_RATE_LIMIT_PER_MINUTE', 60),
    'request_timeout' => env('ANTLER_LOG_REQUEST_TIMEOUT', 2),

    // Laravel-specific options
    'register_exception_handler' => env('ANTLER_REGISTER_EXCEPTION_HANDLER', true),
    'log_queries' => env('ANTLER_LOG_QUERIES', false),
    'log_queue_failures' => env('ANTLER_LOG_QUEUE_FAILURES', true),
    'log_scheduled_task_failures' => env('ANTLER_LOG_SCHEDULED_TASK_FAILURES', true),
    'capture_laravel_context' => env('ANTLER_CAPTURE_LARAVEL_CONTEXT', true),
];
```

### Integration with Laravel's Logging System

The package can integrate with Laravel's built-in logging system by adding a custom channel to your `config/logging.php` file:

```php
'channels' => [
    // Other channels...
    
    'antler' => [
        'driver' => 'custom',
        'via' => \Antler\ErrorLogger\LaravelLoggerFactory::class,
        'level' => env('ANTLER_LOG_LEVEL', 'warning'),
    ],
],
```

You can then use this channel in your application:

```php
Log::channel('antler')->error('Something went wrong', ['context' => 'additional information']);
```

Or set it as your default channel in `.env`:

```
LOG_CHANNEL=antler
```

### Usage with Laravel

#### Basic Usage

Once installed, the logger will automatically capture exceptions thrown in your Laravel application. You can also use it manually:

```php
use Antler\ErrorLogger\Facades\Logger;

// In any controller or service
Logger::error('Payment failed', ['order_id' => $orderId, 'amount' => $amount]);
```

#### Handling Exceptions

The package registers an exception handler to capture all uncaught exceptions in your Laravel application. If you have custom exception handling, you can integrate it:

```php
// In app/Exceptions/Handler.php
public function report(Throwable $exception)
{
    // Let Antler ErrorLogger handle the exception first
    app('antler-error-logger')->error('Uncaught exception', [
        'exception' => $exception,
    ]);
    
    // Then continue with parent reporting
    parent::report($exception);
}
```

#### Logging Queries (For Performance Monitoring)

If you enable the `log_queries` option, the package will log slow database queries:

```php
// In .env
ANTLER_LOG_QUERIES=true
ANTLER_SLOW_QUERY_THRESHOLD=1000 # in milliseconds
```

#### Queue Job Failures

When `log_queue_failures` is enabled, the package will automatically log failed queue jobs with detailed context.

#### Scheduled Task Failures

With `log_scheduled_task_failures` enabled, the package will log any failures in your scheduled tasks.

### Laravel-Specific Context

In addition to the standard context captured by Antler Error Logger, the Laravel integration also captures:

- Current route information
- Controller and action names
- Active middleware
- Current authenticated user (ID, email - no passwords/tokens)
- Laravel version
- Application environment (local, production, etc.)
- Configuration settings (sensitive values redacted)

### Examples in Laravel

#### Logging in a Controller

```php
namespace App\Http\Controllers;

use Antler\ErrorLogger\Facades\Logger;
use App\Models\Order;
use Exception;

class OrderController extends Controller
{
    public function process($id)
    {
        try {
            $order = Order::findOrFail($id);
            
            // Process order...
            
            Logger::info('Order processed successfully', [
                'order_id' => $order->id,
                'amount' => $order->amount,
            ]);
            
            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            Logger::error('Order processing failed', [
                'order_id' => $id,
                'exception' => $e,
            ]);
            
            return response()->json(['status' => 'error'], 500);
        }
    }
}
```

#### Logging in a Queue Job

```php
namespace App\Jobs;

use Antler\ErrorLogger\Facades\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPodcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $podcastId;

    public function __construct($podcastId)
    {
        $this->podcastId = $podcastId;
    }

    public function handle()
    {
        // Process the podcast...
        
        Logger::info('Podcast processed', [
            'podcast_id' => $this->podcastId,
            'processing_time' => $executionTime,
        ]);
    }

    public function failed($exception)
    {
        Logger::error('Podcast processing failed', [
            'podcast_id' => $this->podcastId,
            'exception' => $exception,
        ]);
    }
}
```

### Version-Specific Notes for Laravel

#### Laravel 6.x (PHP 7.2+)

Laravel 6.x introduced semantic versioning, but dropped support for PHP 7.1. When using with Laravel 6.x, ensure you're running PHP 7.2 or higher.

#### Laravel 7.x (PHP 7.2.5+)

PHP 7.2.5 is the minimum requirement for Laravel 7.x. This version works well with the error logger with no special considerations.

#### Laravel 8.x (PHP 7.3+)

Laravel 8.x requires PHP 7.3 or higher. When using with Laravel 8.x, note that model factories changed to a class-based system, which may affect error context collection.

#### Laravel 9.x+ (PHP 8.0.2+)

Laravel 9.x and above require PHP 8.0.2 or higher and include many PHP 8 features. The error logger automatically detects and adapts to PHP 8-specific features like attributes, union types, and named arguments.

### Performance Considerations in Laravel

To minimize performance impact in Laravel, consider these settings:

```php
// Only in production environments
if (app()->environment('production')) {
    // Set more conservative rate limiting
    config(['antler-error-logger.rate_limit_per_minute' => 30]);
    
    // Disable query logging in production
    config(['antler-error-logger.log_queries' => false]);
    
    // Use non-blocking logging where possible
    config(['antler-error-logger.request_timeout' => 1]);
}
```

### Troubleshooting in Laravel Environments

#### Common Issues in Laravel Environments

1. **Permissions issues with log files:**
   Make sure the storage directory is writable by your web server.

   ```bash
   chmod -R 775 storage/logs
   chown -R www-data:www-data storage/logs
   ```

2. **Memory usage in large applications:**
   For Laravel apps with many dependencies, watch memory usage during error processing:

   ```php
   // In config/antler-error-logger.php
   'collect_all_packages' => env('ANTLER_COLLECT_ALL_PACKAGES', false),
   ```

3. **Excessive logging from scheduler:**
   If you run Laravel scheduler frequently, you may want to adjust settings:

   ```php
   // In config/antler-error-logger.php
   'log_scheduled_task_output' => env('ANTLER_LOG_SCHEDULED_TASK_OUTPUT', false),
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

These handlers capture detailed information about errors, including the enhanced stack traces with code context, and send them through all configured transports.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Requirements

- PHP 7.1 or higher
- For remote logging: Either cURL extension or `allow_url_fopen` enabled

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

Antler Error Logger integrates easily with Laravel applications thanks to its singleton pattern. This section covers how to use the error logger directly in Laravel without additional facades or service providers.

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

### Simple Installation

1. Install the package via Composer:

```bash
composer require antlerops/error-logger
```

2. That's it! No service providers or facades required.

### Basic Usage in Laravel

Since the Antler Error Logger uses a singleton pattern, you can access it directly in your Laravel application:

```php
use Antler\ErrorLogger\Logger;
use Antler\ErrorLogger\LoggerConfig;
use Antler\ErrorLogger\LogLevel;

// In any controller, middleware, or service

// Initialize once with your configuration (in a bootstrap file or service provider)
$config = new LoggerConfig([
    'project_hash' => env('ANTLER_PROJECT_HASH', 'your-project-identifier'),
    'remote_endpoint' => env('ANTLER_LOG_ENDPOINT', 'https://your-log-endpoint.com'),
    'log_file_path' => storage_path('logs/antler.log'),
    'min_log_level' => LogLevel::WARNING,
]);

// Get the singleton instance
$logger = Logger::getInstance($config);

// Then use it anywhere in your application
Logger::getInstance()->info('User logged in', ['user_id' => auth()->id()]);
Logger::getInstance()->error('Payment failed', ['order_id' => $orderId]);
```

### Recommended Setup in Laravel

#### Bootstrap Integration

A clean way to integrate the logger is by initializing it in a service provider. You can use Laravel's `AppServiceProvider` or create a dedicated provider:

```php
<?php
// app/Providers/LoggingServiceProvider.php

namespace App\Providers;

use Antler\ErrorLogger\Logger;
use Antler\ErrorLogger\LoggerConfig;
use Antler\ErrorLogger\LogLevel;
use Illuminate\Support\ServiceProvider;

class LoggingServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Configuration for the Antler Error Logger
        $config = new LoggerConfig([
            'project_hash' => env('ANTLER_PROJECT_HASH'),
            'remote_endpoint' => env('ANTLER_LOG_ENDPOINT'),
            'log_file_path' => storage_path('logs/antler.log'),
            'min_log_level' => $this->getLogLevel(),
            'use_remote_logging' => env('ANTLER_LOG_USE_REMOTE_LOGGING', true),
            'use_file_logging' => env('ANTLER_LOG_USE_FILE_LOGGING', true),
            'use_error_log' => env('ANTLER_LOG_USE_ERROR_LOG', true),
            'rate_limit_per_minute' => env('ANTLER_LOG_RATE_LIMIT_PER_MINUTE', 60),
        ]);

        // Initialize the logger singleton
        Logger::getInstance($config);
    }

    /**
     * Convert environment log level to LogLevel constant
     */
    private function getLogLevel()
    {
        $level = strtoupper(env('ANTLER_LOG_MIN_LOG_LEVEL', 'WARNING'));
        
        $levels = [
            'DEBUG' => LogLevel::DEBUG,
            'INFO' => LogLevel::INFO,
            'WARNING' => LogLevel::WARNING,
            'ERROR' => LogLevel::ERROR,
            'CRITICAL' => LogLevel::CRITICAL
        ];
        
        return $levels[$level] ?? LogLevel::WARNING;
    }
}
```

Then register this provider in your `config/app.php`:

```php
'providers' => [
    // Other providers...
    App\Providers\LoggingServiceProvider::class,
],
```

#### Integrating with Laravel's Exception Handler

To capture all exceptions in your Laravel application, you can modify your exception handler:

```php
<?php
// app/Exceptions/Handler.php

namespace App\Exceptions;

use Antler\ErrorLogger\Logger;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    // ... other methods ...

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function report(Throwable $e)
    {
        // Log the exception with Antler Error Logger
        Logger::getInstance()->error('Uncaught exception', [
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'exception' => $e, // The Logger will extract the enhanced stack trace
        ]);
        
        parent::report($e);
    }
}
```

#### Usage in Models, Controllers, and Services

Once initialized, you can use the logger anywhere in your application without dependency injection:

```php
<?php
// In a controller
namespace App\Http\Controllers;

use Antler\ErrorLogger\Logger;
use App\Models\User;
use Exception;

class UserController extends Controller
{
    public function store()
    {
        try {
            $user = User::create(request()->all());
            
            Logger::getInstance()->info('User created', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return redirect()->route('users.show', $user);
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to create user', [
                'input' => request()->except(['password']),
                'exception' => $e
            ]);
            
            return back()->with('error', 'Failed to create user');
        }
    }
}
```

### Using Environment Variables in Laravel

You can configure the logger using Laravel's `.env` file:

```
# Antler Error Logger Configuration
ANTLER_PROJECT_HASH=your-project-identifier
ANTLER_LOG_ENDPOINT=https://your-log-endpoint.com
ANTLER_LOG_MIN_LOG_LEVEL=WARNING
ANTLER_LOG_USE_REMOTE_LOGGING=true
ANTLER_LOG_USE_FILE_LOGGING=true
ANTLER_LOG_USE_ERROR_LOG=true
ANTLER_LOG_RATE_LIMIT_PER_MINUTE=60
```

### Adapting to Laravel Environments

You can adapt the logger configuration based on the Laravel environment:

```php
// In your service provider

public function register()
{
    $config = new LoggerConfig([
        'project_hash' => env('ANTLER_PROJECT_HASH'),
        'remote_endpoint' => env('ANTLER_LOG_ENDPOINT'),
        'log_file_path' => storage_path('logs/antler.log'),
        // Use more verbose logging in local development
        'min_log_level' => app()->environment('local') ? LogLevel::DEBUG : LogLevel::WARNING,
        // Disable remote logging in local development
        'use_remote_logging' => app()->environment('local') ? false : true,
    ]);
    
    Logger::getInstance($config);
}
```

### Examples

#### Logging Database Queries

You can monitor slow database queries by registering a listener:

```php
<?php
// In a service provider

use Antler\ErrorLogger\Logger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

public function boot()
{
    // Log slow queries (over 1000ms)
    if (app()->environment('local', 'staging')) {
        DB::listen(function (QueryExecuted $query) {
            if ($query->time > 1000) {
                Logger::getInstance()->warning('Slow database query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }
}
```

#### Logging Queue Job Failures

To log failed queue jobs:

```php
<?php
// In a service provider or bootstrap file

use Antler\ErrorLogger\Logger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;

Queue::failing(function (JobFailed $event) {
    Logger::getInstance()->error('Queue job failed', [
        'connection' => $event->connectionName,
        'job' => get_class($event->job),
        'exception' => $event->exception,
    ]);
});
```

#### Adding Custom Context for Laravel

You might want to add Laravel-specific context to your logs:

```php
<?php
// Add this to your service provider or middleware

use Antler\ErrorLogger\Logger;
use Illuminate\Support\Facades\Auth;

// Add Laravel-specific context
Logger::getInstance()->info('Application booted', [
    'laravel_version' => app()->version(),
    'environment' => app()->environment(),
    'user_id' => Auth::id() ?? 'guest',
    'current_route' => request()->route() ? request()->route()->getName() : 'unknown',
]);
```

### Performance Considerations

For optimal performance in Laravel applications:

1. **Configure appropriate log levels**:
   - Use `DEBUG` only in development environments
   - Use `WARNING` or `ERROR` in production

2. **Adjust rate limiting**:
   - For high-traffic applications, increase `rate_limit_per_minute`
   - For API-heavy applications, consider setting it higher (e.g., 200-300)

3. **File logging considerations**:
   - Ensure your `storage/logs` directory is properly configured for rotation
   - Consider disabling file logging in favor of remote logging in production

### Troubleshooting

#### Common Issues in Laravel

1. **Logger not properly initialized**:
   - Make sure the `Logger::getInstance($config)` is called early in the application lifecycle
   - Check that your service provider is registered correctly

2. **Permission issues with log files**:
   - Ensure your web server has write permissions for the Laravel storage directory:
   ```bash
   chmod -R 775 storage/logs
   chown -R www-data:www-data storage/logs
   ```

3. **Environment variables not loading**:
   - Verify your `.env` file contains the correct variables
   - Check that Laravel is correctly loading your `.env` file
   - Run `php artisan config:clear` to clear the config cache

4. **Memory usage concerns**:
   - If you're logging a high volume of events, watch your memory usage
   - Consider using the Laravel queue system for processing logs asynchronously

5. **Remote logging timeout**:
   - For remote logging in production, set a low timeout (1-2 seconds) to prevent blocking
   - Enable local file logging as a fallback for remote failures

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

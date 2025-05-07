<?php
namespace Antler\ErrorLogger;

use Throwable;
use ErrorException;

/**
 * Class CodeFrame
 * Provides source code context around error locations.
 */
class CodeFrame
{
    /**
     * Number of lines of context to capture before and after the error line
     */
    private const CONTEXT_LINES = 5;

    /**
     * Get source code context for a file and line number
     *
     * @param string $file File path
     * @param int $line Line number
     * @return array|null Source code context or null if file can't be read
     */
    public static function getContext(string $file, int $line): ?array
    {
        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }

        try {
            $lines = file($file);
            if ($lines === false) {
                return null;
            }

            $totalLines = count($lines);
            $start = max(0, $line - self::CONTEXT_LINES - 1);
            $end = min($totalLines - 1, $line + self::CONTEXT_LINES - 1);

            $context = [];
            for ($i = $start; $i <= $end; $i++) {
                $lineNumber = $i + 1;
                $context[$lineNumber] = [
                    'content' => rtrim($lines[$i]),
                    'is_error_line' => ($lineNumber === $line)
                ];
            }

            return [
                'file' => $file,
                'line' => $line,
                'start_line' => $start + 1,
                'end_line' => $end + 1,
                'context' => $context
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Format an enhanced stack trace with code context
     *
     * @param Throwable $exception The exception to analyze
     * @return array Enhanced stack trace with code context
     */
    public static function enhancedTraceFromException(Throwable $exception): array
    {
        $frames = [];

        // Add the exception origin as the first frame
        $frames[] = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => null,
            'class' => null,
            'type' => null,
            'args' => [],
            'code_context' => self::getContext($exception->getFile(), $exception->getLine())
        ];

        // Add stack trace frames
        $trace = $exception->getTrace();
        foreach ($trace as $frame) {
            $enhancedFrame = [
                'file' => $frame['file'] ?? '[internal function]',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
                'args' => self::sanitizeArgs($frame['args'] ?? []),
                'code_context' => null
            ];

            if (isset($frame['file'], $frame['line'])) {
                $enhancedFrame['code_context'] = self::getContext($frame['file'], $frame['line']);
            }

            $frames[] = $enhancedFrame;
        }

        return [
            'frames' => $frames,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode()
        ];
    }

    /**
     * Get an enhanced stack trace for a regular error (not exception)
     *
     * @param string $file File where the error occurred
     * @param int $line Line number where the error occurred
     * @param string $message Error message
     * @param int $severity Error severity
     * @return array Enhanced trace with code context
     */
    public static function enhancedTraceFromError(string $file, int $line, string $message, int $severity): array
    {
        // Create a temporary exception to get a stack trace
        $exception = new ErrorException($message, 0, $severity, $file, $line);
        return self::enhancedTraceFromException($exception);
    }

    /**
     * Sanitize function arguments (limit deep nesting, large arrays, etc.)
     *
     * @param array $args Function arguments
     * @return array Sanitized arguments
     */
    private static function sanitizeArgs(array $args): array
    {
        $result = [];

        foreach ($args as $key => $value) {
            // Truncate or sanitize based on type
            if (is_string($value)) {
                $result[$key] = strlen($value) > 100 ? substr($value, 0, 97) . '...' : $value;
            } elseif (is_array($value)) {
                $result[$key] = '[array(' . count($value) . ')]';
            } elseif (is_object($value)) {
                $result[$key] = '[object(' . get_class($value) . ')]';
            } elseif (is_resource($value)) {
                $result[$key] = '[resource(' . get_resource_type($value) . ')]';
            } elseif (is_bool($value)) {
                $result[$key] = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $result[$key] = 'null';
            } else {
                // For int, float, etc.
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

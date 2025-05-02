<?php
namespace Antler\ErrorLogger;

class LogLevel
{
    public const DEBUG = 100;
    public const INFO = 200;
    public const WARNING = 300;
    public const ERROR = 400;
    public const CRITICAL = 500;

    /**
     * Converts a log level integer value to its corresponding string representation.
     *
     * @param int $level The log level to be converted. This should match a predefined log level constant.
     * @return string The string representation of the given log level. If the level is unrecognized, 'UNKNOWN' is returned.
     */
    public static function toString(int $level): string
    {
        return match($level) {
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::WARNING => 'WARNING',
            self::ERROR => 'ERROR',
            self::CRITICAL => 'CRITICAL',
            default => 'UNKNOWN',
        };
    }
}

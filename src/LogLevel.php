<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use TinyBlocks\Logger\Exceptions\UnknownLogLevel;

/**
 * The PSR-3 log levels, ordered from the least to the most severe.
 */
enum LogLevel: string
{
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case NOTICE = 'NOTICE';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case CRITICAL = 'CRITICAL';
    case ALERT = 'ALERT';
    case EMERGENCY = 'EMERGENCY';

    /**
     * Creates a LogLevel from the level argument of a PSR-3 log call.
     *
     * @param mixed $level The level as received by the PSR-3 contract, in any letter case.
     * @return LogLevel The matching level.
     * @throws UnknownLogLevel If the value is outside the supported PSR-3 set.
     */
    public static function fromPsrLevel(mixed $level): LogLevel
    {
        $logLevel = LogLevel::tryFrom(strtoupper((string)$level));

        if (is_null($logLevel)) {
            $template = 'Unknown log level: %s.';

            throw new UnknownLogLevel(message: sprintf($template, (string)$level));
        }

        return $logLevel;
    }

    /**
     * Returns the severity rank of the level, ascending from debug to emergency.
     *
     * @return int The severity rank.
     */
    public function severity(): int
    {
        return match ($this) {
            self::DEBUG     => 0,
            self::INFO      => 1,
            self::NOTICE    => 2,
            self::WARNING   => 3,
            self::ERROR     => 4,
            self::CRITICAL  => 5,
            self::ALERT     => 6,
            self::EMERGENCY => 7
        };
    }

    /**
     * Tells whether the level is at least as severe as the given threshold.
     *
     * @param LogLevel $threshold The lowest severity that is still emitted.
     * @return bool Whether the level reaches the threshold.
     */
    public function isAtLeast(LogLevel $threshold): bool
    {
        return $this->severity() >= $threshold->severity();
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

/**
 * A single log entry, as recorded before it reaches any output.
 */
final readonly class LogEntry
{
    private function __construct(
        public string $key,
        public LogLevel $level,
        public array $payload,
        public ?Correlation $correlation
    ) {
    }

    /**
     * Creates a LogEntry from its key, level, payload, and correlation.
     *
     * @param string $key The message key identifying the event.
     * @param LogLevel $level The severity the entry was logged at.
     * @param array<string, mixed> $payload The context data carried by the entry.
     * @param Correlation|null $correlation The correlation bound to the logger, or null when none is.
     * @return LogEntry The created instance.
     */
    public static function of(string $key, LogLevel $level, array $payload, ?Correlation $correlation): LogEntry
    {
        return new LogEntry(key: $key, level: $level, payload: $payload, correlation: $correlation);
    }
}

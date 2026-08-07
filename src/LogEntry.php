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
        public ?LogContext $context,
        public array $payload
    ) {
    }

    /**
     * Creates a LogEntry from its key, level, correlation context, and payload.
     *
     * @param string $key The message key identifying the event.
     * @param LogLevel $level The severity the entry was logged at.
     * @param LogContext|null $context The correlation context bound to the logger, or null when none is.
     * @param array<string, mixed> $payload The context data carried by the entry.
     * @return LogEntry The created instance.
     */
    public static function from(string $key, LogLevel $level, ?LogContext $context, array $payload): LogEntry
    {
        return new LogEntry(key: $key, level: $level, context: $context, payload: $payload);
    }
}

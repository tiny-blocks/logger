<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

/**
 * Correlation context carried across log entries.
 */
final readonly class LogContext
{
    private function __construct(public string $correlationId)
    {
    }

    /**
     * Creates a LogContext from a correlation identifier.
     *
     * @param string $correlationId The correlation identifier shared across related log entries.
     * @return LogContext The created instance.
     */
    public static function from(string $correlationId): LogContext
    {
        return new LogContext(correlationId: $correlationId);
    }
}

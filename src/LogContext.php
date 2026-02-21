<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

final readonly class LogContext
{
    private function __construct(public string $correlationId)
    {
    }

    public static function from(string $correlationId): LogContext
    {
        return new LogContext(correlationId: $correlationId);
    }
}

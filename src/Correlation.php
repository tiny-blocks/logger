<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

/**
 * Correlation context carried across log entries.
 */
final readonly class Correlation
{
    private function __construct(public string $correlationId)
    {
    }

    /**
     * Creates a Correlation from a correlation identifier.
     *
     * @param string $correlationId The correlation identifier shared across related log entries.
     * @return Correlation The created instance.
     */
    public static function from(string $correlationId): Correlation
    {
        return new Correlation(correlationId: $correlationId);
    }
}

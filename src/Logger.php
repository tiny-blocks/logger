<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use Psr\Log\LoggerInterface;

/**
 * Defines a structured logging contract with support for correlation tracking and data redaction.
 *
 * Extends PSR-3 {@see LoggerInterface} to ensure compatibility with any PSR-3 consumer.
 *
 * Implementations must support immutable context propagation: calling {@see withContext}
 * returns a new instance without mutating the original.
 */
interface Logger extends LoggerInterface
{
    /**
     * Creates a new Logger instance bound to the given correlation context.
     *
     * The original instance remains unchanged.
     *
     * @param LogContext $context The log context containing the correlation ID.
     * @return static A new Logger instance bound to the given context.
     */
    public function withContext(LogContext $context): static;
}

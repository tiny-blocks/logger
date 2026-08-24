<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use Psr\Log\LoggerInterface;

/**
 * Defines a structured logging contract with support for correlation tracking and data redaction.
 *
 * Extends PSR-3 {@see LoggerInterface} to ensure compatibility with any PSR-3 consumer.
 *
 * Implementations must support immutable correlation propagation: calling {@see withCorrelation}
 * returns a new instance without mutating the original.
 */
interface Logger extends LoggerInterface
{
    /**
     * Creates a new Logger instance bound to the given correlation.
     *
     * <p>The original instance remains unchanged. An implementation returns its own type, so
     * declaring the concrete class name is the expected form.</p>
     *
     * @param Correlation $correlation The correlation containing the correlation ID.
     * @return Logger A new Logger instance bound to the given correlation.
     */
    public function withCorrelation(Correlation $correlation): Logger;
}

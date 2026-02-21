<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use TinyBlocks\Logger\Internal\LogContext;

/**
 * Defines a structured logging contract with support for correlation tracking and data redaction.
 */
interface Logger
{
    /**
     * Creates a new Logger instance with the given correlation context.
     *
     * @param LogContext $context The log context containing the correlation ID.
     * @return static A new Logger instance bound to the given context.
     */
    public function withContext(LogContext $context): static;

    /**
     * Logs an informational message.
     *
     * @param string $key A key identifying the log entry.
     * @param array $data Optional structured data to include.
     */
    public function info(string $key, array $data = []): void;

    /**
     * Logs a warning message.
     *
     * @param string $key A key identifying the log entry.
     * @param array $data Optional structured data to include.
     */
    public function warning(string $key, array $data = []): void;

    /**
     * Logs an error message.
     *
     * @param string $key A key identifying the log entry.
     * @param array $data Optional structured data to include.
     */
    public function error(string $key, array $data = []): void;
}

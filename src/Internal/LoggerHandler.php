<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal;

use Psr\Log\LoggerInterface;
use TinyBlocks\Logger\Internal\Redactor\Redactions;
use TinyBlocks\Logger\Logger;
use TinyBlocks\Logger\LogLevel;

/**
 * Structured logger implementation with support for correlation tracking and data redaction.
 */
final readonly class LoggerHandler implements Logger
{
    private function __construct(
        private LoggerInterface $logger,
        private LogFormatter $formatter,
        private Redactions $redactions,
        private ?LogContext $context
    ) {
    }

    /**
     * Creates a new LoggerHandler.
     *
     * @param LoggerInterface $logger The PSR-3 logger to delegate log writing to.
     * @param string $component The application component name.
     * @param Redactions|null $redactions Optional redaction rules for sensitive data.
     * @return LoggerHandler A new LoggerHandler instance.
     */
    public static function create(
        LoggerInterface $logger,
        string $component,
        ?Redactions $redactions = null
    ): LoggerHandler {
        return new LoggerHandler(
            logger: $logger,
            formatter: new LogFormatter(component: $component),
            redactions: $redactions ?? Redactions::createEmpty(),
            context: null
        );
    }

    public function withContext(LogContext $context): static
    {
        return new static(
            logger: $this->logger,
            formatter: $this->formatter,
            redactions: $this->redactions,
            context: $context
        );
    }

    public function info(string $key, array $data = []): void
    {
        $this->log(level: LogLevel::INFO, key: $key, data: $data);
    }

    public function warning(string $key, array $data = []): void
    {
        $this->log(level: LogLevel::WARNING, key: $key, data: $data);
    }

    public function error(string $key, array $data = []): void
    {
        $this->log(level: LogLevel::ERROR, key: $key, data: $data);
    }

    private function log(LogLevel $level, string $key, array $data): void
    {
        $redactedData = $this->redactions->applyTo(data: $data);
        $formatted = $this->formatter->format(
            level: $level,
            key: $key,
            data: $redactedData,
            context: $this->context
        );

        $this->logger->log(level: strtolower($level->value), message: $formatted);
    }
}

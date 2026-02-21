<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use Psr\Log\LoggerTrait;
use Stringable;
use TinyBlocks\Logger\Internal\LogFormatter;
use TinyBlocks\Logger\Internal\Redactor\Redactions;
use TinyBlocks\Logger\Internal\Stream\LogStream;

final readonly class StructuredLogger implements Logger
{
    use LoggerTrait;

    private function __construct(
        private LogStream $stream,
        private ?LogContext $context,
        private LogFormatter $formatter,
        private Redactions $redactions
    ) {
    }

    public static function create(): StructuredLoggerBuilder
    {
        return new StructuredLoggerBuilder();
    }

    public static function build(
        LogStream $stream,
        ?LogContext $context,
        LogFormatter $formatter,
        Redactions $redactions
    ): StructuredLogger {
        return new StructuredLogger(
            stream: $stream,
            context: $context,
            formatter: $formatter,
            redactions: $redactions
        );
    }

    public function withContext(LogContext $context): static
    {
        return new StructuredLogger(
            stream: $this->stream,
            context: $context,
            formatter: $this->formatter,
            redactions: $this->redactions
        );
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $logLevel = LogLevel::from(strtoupper((string)$level));
        $redactedData = $this->redactions->applyTo(data: $context);

        $formatted = $this->formatter->format(
            key: (string)$message,
            data: $redactedData,
            level: $logLevel,
            context: $this->context
        );

        $this->stream->write(content: $formatted);
    }
}

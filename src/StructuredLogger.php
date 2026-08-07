<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use Psr\Log\LoggerTrait;
use Stringable;
use TinyBlocks\Logger\Exceptions\UnknownLogLevel;
use TinyBlocks\Logger\Internal\LogFormatter;
use TinyBlocks\Logger\Internal\Redactor\Redactions;
use TinyBlocks\Logger\Internal\Stream\LogStream;

/**
 * Structured logger that writes redacted, formatted log entries to a stream.
 */
final readonly class StructuredLogger implements Logger
{
    use LoggerTrait;

    private function __construct(
        private LogStream $stream,
        private ?LogContext $context,
        private LogFormatter $formatter,
        private Redactions $redactions,
        private LogLevel $minimumLevel
    ) {
    }

    /**
     * Creates a StructuredLogger from its stream, context, template, component, level, and redactions.
     *
     * @param mixed $stream The stream the logger writes to, or null to fall back to standard error.
     * @param LogContext|null $context The correlation context, or null when none is bound.
     * @param string $template The format template, or an empty string to use the default template.
     * @param string $component The component name identifying the log source.
     * @param LogLevel $minimumLevel The lowest severity that is written, quieter entries are discarded.
     * @param Redaction ...$redactions The redaction strategies applied to context data before writing.
     * @return StructuredLogger The created logger instance.
     */
    public static function from(
        mixed $stream,
        ?LogContext $context,
        string $template,
        string $component,
        LogLevel $minimumLevel,
        Redaction ...$redactions
    ): StructuredLogger {
        $formatter = $template === ''
            ? LogFormatter::fromComponent(component: $component)
            : LogFormatter::fromTemplate(template: $template, component: $component);

        return new StructuredLogger(
            stream: LogStream::from(resource: $stream),
            context: $context,
            formatter: $formatter,
            redactions: Redactions::createFrom(elements: $redactions),
            minimumLevel: $minimumLevel
        );
    }

    /**
     * Creates a StructuredLoggerBuilder.
     *
     * @return StructuredLoggerBuilder A new builder for configuring a StructuredLogger.
     */
    public static function create(): StructuredLoggerBuilder
    {
        return new StructuredLoggerBuilder();
    }

    /**
     * Writes a log entry to the stream when its severity reaches the configured minimum level.
     *
     * @param mixed $level The severity, as received by the PSR-3 contract.
     * @param string|Stringable $message The message key identifying the event.
     * @param array<string, mixed> $context The context data carried by the entry.
     * @throws UnknownLogLevel If the level is outside the supported PSR-3 set.
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $logLevel = LogLevel::fromPsrLevel(level: $level);

        if (!$logLevel->isAtLeast(threshold: $this->minimumLevel)) {
            return;
        }

        $formatted = $this->formatter->format(
            key: (string)$message,
            level: $logLevel,
            payload: $this->redactions->applyTo(payload: $context),
            context: $this->context
        );

        $this->stream->write(content: $formatted);
    }

    public function withContext(LogContext $context): StructuredLogger
    {
        return new StructuredLogger(
            stream: $this->stream,
            context: $context,
            formatter: $this->formatter,
            redactions: $this->redactions,
            minimumLevel: $this->minimumLevel
        );
    }
}

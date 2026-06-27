<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use Psr\Log\LoggerTrait;
use Stringable;
use TinyBlocks\Logger\Exceptions\UnknownLogLevel;
use TinyBlocks\Logger\Internal\LogFormatter;
use TinyBlocks\Logger\Internal\LogLevel;
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
        private Redactions $redactions
    ) {
    }

    /**
     * Creates a StructuredLogger from its stream, context, template, component, and redactions.
     *
     * @param mixed $stream The stream the logger writes to, or null to fall back to standard error.
     * @param LogContext|null $context The correlation context, or null when none is bound.
     * @param string $template The format template, or an empty string to use the default template.
     * @param string $component The component name identifying the log source.
     * @param Redaction ...$redactions The redaction strategies applied to context data before writing.
     * @return StructuredLogger The created logger instance.
     */
    public static function from(
        mixed $stream,
        ?LogContext $context,
        string $template,
        string $component,
        Redaction ...$redactions
    ): StructuredLogger {
        $formatter = $template === ''
            ? LogFormatter::fromComponent(component: $component)
            : LogFormatter::fromTemplate(template: $template, component: $component);

        return new StructuredLogger(
            stream: LogStream::from(resource: $stream),
            context: $context,
            formatter: $formatter,
            redactions: Redactions::createFrom(elements: $redactions)
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

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $logLevel = LogLevel::tryFrom(strtoupper((string)$level));

        if (is_null($logLevel)) {
            $template = 'Unknown log level: %s.';

            throw new UnknownLogLevel(message: sprintf($template, (string)$level));
        }

        $redactedPayload = $this->redactions->applyTo(payload: $context);

        $formatted = $this->formatter->format(
            key: (string)$message,
            level: $logLevel,
            context: $this->context,
            payload: $redactedPayload
        );

        $this->stream->write(content: $formatted);
    }

    public function withContext(LogContext $context): StructuredLogger
    {
        return new StructuredLogger(
            stream: $this->stream,
            context: $context,
            formatter: $this->formatter,
            redactions: $this->redactions
        );
    }
}

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
final readonly class StreamLogger implements Logger
{
    use LoggerTrait;

    private function __construct(
        private LogStream $stream,
        private LogFormatter $formatter,
        private Redactions $redactions,
        private ?Correlation $correlation,
        private LogLevel $minimumLevel
    ) {
    }

    /**
     * Creates a StreamLogger from what its builder describes.
     *
     * @param StreamLoggerBuilder $builder The stream, correlation, template, component, level, and redactions.
     * @return StreamLogger The created logger instance.
     */
    public static function from(StreamLoggerBuilder $builder): StreamLogger
    {
        $formatter = $builder->template === ''
            ? LogFormatter::fromComponent(component: $builder->component)
            : LogFormatter::fromTemplate(template: $builder->template, component: $builder->component);

        return new StreamLogger(
            stream: LogStream::from(resource: $builder->stream),
            formatter: $formatter,
            redactions: Redactions::createFrom(elements: $builder->redactions),
            correlation: $builder->correlation,
            minimumLevel: $builder->minimumLevel
        );
    }

    /**
     * Creates a StreamLoggerBuilder.
     *
     * @return StreamLoggerBuilder A new builder for configuring a StreamLogger.
     */
    public static function builder(): StreamLoggerBuilder
    {
        return StreamLoggerBuilder::default();
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
            correlation: $this->correlation
        );

        $this->stream->write(content: $formatted);
    }

    public function withCorrelation(Correlation $correlation): StreamLogger
    {
        return new StreamLogger(
            stream: $this->stream,
            formatter: $this->formatter,
            redactions: $this->redactions,
            correlation: $correlation,
            minimumLevel: $this->minimumLevel
        );
    }
}

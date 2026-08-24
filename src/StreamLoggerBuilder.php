<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use TinyBlocks\Logger\Redactions\Redaction;

/**
 * Fluent description of a StreamLogger: a stream, correlation, template, component, minimum level,
 * and redactions.
 *
 * <p>Immutable, so every <code>with</code> returns a copy and the builder it came from still
 * describes what it described before.</p>
 */
final readonly class StreamLoggerBuilder
{
    private function __construct(
        public mixed $stream,
        public string $template,
        public string $component,
        public array $redactions,
        public ?Correlation $correlation,
        public LogLevel $minimumLevel
    ) {
    }

    /**
     * Creates a StreamLoggerBuilder with the baseline description.
     *
     * @return StreamLoggerBuilder A builder writing to standard error, with no redaction and no threshold.
     */
    public static function default(): StreamLoggerBuilder
    {
        return new StreamLoggerBuilder(
            stream: null,
            template: '',
            component: '',
            redactions: [],
            correlation: null,
            minimumLevel: LogLevel::DEBUG
        );
    }

    /**
     * Builds a StreamLogger from what the builder describes.
     *
     * @return StreamLogger The configured logger instance.
     */
    public function build(): StreamLogger
    {
        return StreamLogger::from(builder: $this);
    }

    /**
     * Returns a copy of the builder with the stream replaced.
     *
     * @param mixed $stream The stream log entries are written to.
     * @return StreamLoggerBuilder A copy of the builder with the stream set.
     */
    public function withStream(mixed $stream): StreamLoggerBuilder
    {
        return new StreamLoggerBuilder(
            stream: $stream,
            template: $this->template,
            component: $this->component,
            redactions: $this->redactions,
            correlation: $this->correlation,
            minimumLevel: $this->minimumLevel
        );
    }

    /**
     * Returns a copy of the builder with the format template replaced.
     *
     * @param string $template The format template applied to every entry.
     * @return StreamLoggerBuilder A copy of the builder with the template set.
     */
    public function withTemplate(string $template): StreamLoggerBuilder
    {
        return new StreamLoggerBuilder(
            stream: $this->stream,
            template: $template,
            component: $this->component,
            redactions: $this->redactions,
            correlation: $this->correlation,
            minimumLevel: $this->minimumLevel
        );
    }

    /**
     * Returns a copy of the builder with the component replaced.
     *
     * @param string $component The component name identifying the log source.
     * @return StreamLoggerBuilder A copy of the builder with the component set.
     */
    public function withComponent(string $component): StreamLoggerBuilder
    {
        return new StreamLoggerBuilder(
            stream: $this->stream,
            template: $this->template,
            component: $component,
            redactions: $this->redactions,
            correlation: $this->correlation,
            minimumLevel: $this->minimumLevel
        );
    }

    /**
     * Returns a copy of the builder with the redactions added to the ones already described.
     *
     * @param Redaction ...$redactions The redaction strategies applied before writing.
     * @return StreamLoggerBuilder A copy of the builder carrying the previous and the new redactions.
     */
    public function withRedactions(Redaction ...$redactions): StreamLoggerBuilder
    {
        return new StreamLoggerBuilder(
            stream: $this->stream,
            template: $this->template,
            component: $this->component,
            redactions: array_merge($this->redactions, $redactions),
            correlation: $this->correlation,
            minimumLevel: $this->minimumLevel
        );
    }

    /**
     * Returns a copy of the builder with the correlation replaced.
     *
     * @param Correlation $correlation The correlation shared across log entries.
     * @return StreamLoggerBuilder A copy of the builder with the correlation set.
     */
    public function withCorrelation(Correlation $correlation): StreamLoggerBuilder
    {
        return new StreamLoggerBuilder(
            stream: $this->stream,
            template: $this->template,
            component: $this->component,
            redactions: $this->redactions,
            correlation: $correlation,
            minimumLevel: $this->minimumLevel
        );
    }

    /**
     * Returns a copy of the builder with the minimum level replaced.
     *
     * @param LogLevel $minimumLevel The lowest severity that is written.
     * @return StreamLoggerBuilder A copy of the builder with the minimum level set.
     */
    public function withMinimumLevel(LogLevel $minimumLevel): StreamLoggerBuilder
    {
        return new StreamLoggerBuilder(
            stream: $this->stream,
            template: $this->template,
            component: $this->component,
            redactions: $this->redactions,
            correlation: $this->correlation,
            minimumLevel: $minimumLevel
        );
    }
}

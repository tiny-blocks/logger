<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

/**
 * Fluent builder that assembles a StructuredLogger from a stream, context, template, component, and redactions.
 */
final readonly class StructuredLoggerBuilder
{
    public function __construct(
        private mixed $stream = null,
        private ?LogContext $context = null,
        private string $template = '',
        private string $component = '',
        private array $redactions = []
    ) {
    }

    /**
     * Builds a StructuredLogger from the configured stream, context, template, component, and redactions.
     *
     * @return StructuredLogger The configured logger instance.
     */
    public function build(): StructuredLogger
    {
        return StructuredLogger::from(
            $this->stream,
            $this->context,
            $this->template,
            $this->component,
            ...$this->redactions
        );
    }

    /**
     * Returns a copy of the builder with the stream replaced.
     *
     * @param mixed $stream The stream the logger writes to.
     * @return StructuredLoggerBuilder A copy of the builder with the stream set.
     */
    public function withStream(mixed $stream): StructuredLoggerBuilder
    {
        return new StructuredLoggerBuilder(
            stream: $stream,
            context: $this->context,
            template: $this->template,
            component: $this->component,
            redactions: $this->redactions
        );
    }

    /**
     * Returns a copy of the builder with the context replaced.
     *
     * @param LogContext $context The context shared across log entries.
     * @return StructuredLoggerBuilder A copy of the builder with the context set.
     */
    public function withContext(LogContext $context): StructuredLoggerBuilder
    {
        return new StructuredLoggerBuilder(
            stream: $this->stream,
            context: $context,
            template: $this->template,
            component: $this->component,
            redactions: $this->redactions
        );
    }

    /**
     * Returns a copy of the builder with the template replaced.
     *
     * @param string $template The format template for rendering entries.
     * @return StructuredLoggerBuilder A copy of the builder with the template set.
     */
    public function withTemplate(string $template): StructuredLoggerBuilder
    {
        return new StructuredLoggerBuilder(
            stream: $this->stream,
            context: $this->context,
            template: $template,
            component: $this->component,
            redactions: $this->redactions
        );
    }

    /**
     * Returns a copy of the builder with the component replaced.
     *
     * @param string $component The component name identifying the log source.
     * @return StructuredLoggerBuilder A copy of the builder with the component set.
     */
    public function withComponent(string $component): StructuredLoggerBuilder
    {
        return new StructuredLoggerBuilder(
            stream: $this->stream,
            context: $this->context,
            template: $this->template,
            component: $component,
            redactions: $this->redactions
        );
    }

    /**
     * Returns a copy of the builder with the given redactions appended.
     *
     * @param Redaction ...$redactions The redaction strategies to apply to log data.
     * @return StructuredLoggerBuilder A copy of the builder with the redactions appended.
     */
    public function withRedactions(Redaction ...$redactions): StructuredLoggerBuilder
    {
        return new StructuredLoggerBuilder(
            stream: $this->stream,
            context: $this->context,
            template: $this->template,
            component: $this->component,
            redactions: array_merge($this->redactions, $redactions)
        );
    }
}

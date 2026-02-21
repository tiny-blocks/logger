<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use TinyBlocks\Logger\Internal\LogFormatter;
use TinyBlocks\Logger\Internal\Redactor\Redactions;
use TinyBlocks\Logger\Internal\Stream\LogStream;

final class StructuredLoggerBuilder
{
    private mixed $stream = null;
    private ?LogContext $context = null;
    private string $template = '';
    private string $component = '';

    /** @var Redaction[] */
    private array $redactions = [];

    public function withStream(mixed $stream): StructuredLoggerBuilder
    {
        $this->stream = $stream;
        return $this;
    }

    public function withContext(LogContext $context): StructuredLoggerBuilder
    {
        $this->context = $context;
        return $this;
    }

    public function withTemplate(string $template): StructuredLoggerBuilder
    {
        $this->template = $template;
        return $this;
    }

    public function withComponent(string $component): StructuredLoggerBuilder
    {
        $this->component = $component;
        return $this;
    }

    public function withRedactions(Redaction ...$redactions): StructuredLoggerBuilder
    {
        $this->redactions = array_merge($this->redactions, $redactions);
        return $this;
    }

    public function build(): StructuredLogger
    {
        $formatter = empty($this->template)
            ? LogFormatter::fromComponent(component: $this->component)
            : LogFormatter::fromTemplate(component: $this->component, template: $this->template);

        return StructuredLogger::build(
            stream: LogStream::from(resource: $this->stream),
            context: $this->context,
            formatter: $formatter,
            redactions: Redactions::createFrom(elements: $this->redactions)
        );
    }
}

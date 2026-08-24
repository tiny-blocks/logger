<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Metrics;

use TinyBlocks\Logger\Correlation;
use TinyBlocks\Logger\Internal\Redactor\Redactions;
use TinyBlocks\Logger\Internal\Stream\LogStream;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricFormat;

final readonly class MetricWriter
{
    public function __construct(
        private MetricFormat $format,
        private LogStream $stream,
        private string $component,
        private Redactions $redactions
    ) {
    }

    public function write(Metric $metric, ?Correlation $correlation): void
    {
        $line = MetricLine::from(
            format: $this->format,
            metric: $metric,
            component: $this->component,
            redactions: $this->redactions,
            correlationId: ($correlation->correlationId ?? '')
        );

        $this->stream->write(content: $line->toString());
    }
}

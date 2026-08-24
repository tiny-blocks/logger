<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Metrics;

use TinyBlocks\Logger\Exceptions\UnboundedDimension;

/**
 * Renders a {@see Metric} as the payload a metric backend reads.
 *
 * <p>The seam that keeps the metric itself free of any backend. An implementation owns the shape
 * of the record, the spelling of the units, and whichever dimension names it refuses, and lives in
 * the folder of the backend it writes for.</p>
 */
interface MetricFormat
{
    /**
     * Renders the metric as the payload to be logged.
     *
     * @param Metric $metric The metric to render.
     * @return array<string, mixed> The payload the backend reads.
     * @throws UnboundedDimension If the metric declares a dimension the format refuses.
     */
    public function format(Metric $metric): array;
}

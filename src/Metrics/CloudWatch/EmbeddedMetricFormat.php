<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Metrics\CloudWatch;

use TinyBlocks\Logger\Exceptions\UnboundedDimension;
use TinyBlocks\Logger\Internal\Metrics\CloudWatchEnvelope;
use TinyBlocks\Logger\Internal\Metrics\UnboundedDimensions;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricFormat;

/**
 * The Amazon CloudWatch Embedded Metric Format (EMF).
 *
 * <p>EMF publishes a metric by writing a log record that carries the metric inside it, which spares
 * the publish API call. It never spares the custom metric tariff: every distinct pairing of metric
 * name and dimension set is a series, and a series is billed. Which dimension names carry an
 * unbounded number of values belongs to whoever emits, so this format refuses none until told which
 * ones to refuse, and the refusal names the alternative.</p>
 *
 * <p>The record has to reach the log stream as a JSON object on its own line, because that is what
 * the agent parses, and that is the line a telemetry logger writes for every metric.</p>
 */
final readonly class EmbeddedMetricFormat implements MetricFormat
{
    private function __construct(private UnboundedDimensions $unboundedDimensions)
    {
    }

    /**
     * Creates the format refusing no dimension name.
     *
     * @return EmbeddedMetricFormat The created instance.
     */
    public static function default(): EmbeddedMetricFormat
    {
        return new EmbeddedMetricFormat(unboundedDimensions: UnboundedDimensions::none());
    }

    public function format(Metric $metric): array
    {
        $dimension = $this->unboundedDimensions->firstIn(dimensions: $metric->dimensions);

        if (!is_null($dimension)) {
            throw new UnboundedDimension(dimension: $dimension);
        }

        return new CloudWatchEnvelope(metric: $metric)->toPayload();
    }

    /**
     * Returns a copy of the format refusing the given dimension names.
     *
     * <p>Which names are unbounded is a property of the emitting domain and not of EMF, so the list
     * is declared here rather than carried by the library.</p>
     *
     * @param string ...$names The dimension names refused as unbounded.
     * @return EmbeddedMetricFormat A copy refusing those names on top of the ones already refused.
     */
    public function withUnboundedDimensions(string ...$names): EmbeddedMetricFormat
    {
        return new EmbeddedMetricFormat(unboundedDimensions: $this->unboundedDimensions->and(...$names));
    }
}

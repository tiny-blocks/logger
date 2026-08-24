<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Metrics;

use TinyBlocks\Logger\Exceptions\BlankMetricIdentifier;

/**
 * The name a metric is published under.
 *
 * <p>It names the series and, in a record that carries the metric inside it, it is also the key the
 * value sits at. A name that carries nothing leaves a record whose value has no series to belong
 * to, so the type refuses one rather than let the emptiness travel.</p>
 */
final readonly class MetricName
{
    private function __construct(public string $value)
    {
    }

    /**
     * Creates a name from its text.
     *
     * @param string $value The metric name (e.g., OfferAccepted).
     * @return MetricName The created instance.
     * @throws BlankMetricIdentifier If the text carries nothing.
     */
    public static function from(string $value): MetricName
    {
        if (trim($value) === '') {
            throw new BlankMetricIdentifier(identifier: 'name');
        }

        return new MetricName(value: $value);
    }
}

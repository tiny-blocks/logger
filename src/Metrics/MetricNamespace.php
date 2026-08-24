<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Metrics;

use TinyBlocks\Logger\Exceptions\BlankMetricIdentifier;

/**
 * The namespace a metric series belongs to.
 *
 * <p>It is what separates one application's series from another's in the same backend, so a
 * namespace that carries nothing puts the series where no one is looking. The type refuses one.</p>
 */
final readonly class MetricNamespace
{
    private function __construct(public string $value)
    {
    }

    /**
     * Creates a namespace from its text.
     *
     * @param string $value The namespace (e.g., Acme/Waitlist).
     * @return MetricNamespace The created instance.
     * @throws BlankMetricIdentifier If the text carries nothing.
     */
    public static function from(string $value): MetricNamespace
    {
        if (trim($value) === '') {
            throw new BlankMetricIdentifier(identifier: 'namespace');
        }

        return new MetricNamespace(value: $value);
    }
}

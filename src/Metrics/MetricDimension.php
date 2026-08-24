<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Metrics;

use TinyBlocks\Logger\Exceptions\BlankMetricIdentifier;

/**
 * One axis a metric is broken down by, and the value it takes on that axis.
 *
 * <p>Every distinct set of dimension values is a series of its own, which a backend indexes and
 * bills for, so a dimension is the expensive half of a record. A name that carries nothing declares
 * an axis the backend cannot resolve, and the type refuses one.</p>
 */
final readonly class MetricDimension
{
    private function __construct(public string $name, public string $value)
    {
    }

    /**
     * Creates a dimension from its name and its value.
     *
     * @param string $name The dimension name (e.g., plan).
     * @param string $value The value it takes (e.g., saas).
     * @return MetricDimension The created instance.
     * @throws BlankMetricIdentifier If the name carries nothing.
     */
    public static function of(string $name, string $value): MetricDimension
    {
        if (trim($name) === '') {
            throw new BlankMetricIdentifier(identifier: 'dimension name');
        }

        return new MetricDimension(name: $name, value: $value);
    }
}

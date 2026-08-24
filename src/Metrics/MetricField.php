<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Metrics;

use TinyBlocks\Logger\Exceptions\BlankMetricIdentifier;

/**
 * One attribute that rides along in the record without becoming a series.
 *
 * <p>It is the cheap half: queryable where the record lands, and free of the cardinality that makes
 * a dimension expensive, which is where a granular attribute belongs. A name that carries nothing
 * leaves a value nobody can query for, and the type refuses one.</p>
 */
final readonly class MetricField
{
    private function __construct(public string $name, public string|int|float $value)
    {
    }

    /**
     * Creates a field from its name and its value.
     *
     * @param string $name The field name (e.g., tenant_id).
     * @param string|int|float $value The value it carries.
     * @return MetricField The created instance.
     * @throws BlankMetricIdentifier If the name carries nothing.
     */
    public static function of(string $name, string|int|float $value): MetricField
    {
        if (trim($name) === '') {
            throw new BlankMetricIdentifier(identifier: 'field name');
        }

        return new MetricField(name: $name, value: $value);
    }
}

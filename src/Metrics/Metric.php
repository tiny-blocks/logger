<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Metrics;

use TinyBlocks\Logger\Exceptions\BlankMetricIdentifier;
use TinyBlocks\Logger\Exceptions\DuplicateMetricIdentifier;

/**
 * A measurement the application publishes, expressed without reference to any metric backend.
 *
 * <p>A metric carries a namespace, a name, a value in a unit, the dimensions it is broken down by,
 * and the fields that travel with it. What separates the two last ones is cost, not shape: a
 * dimension is what a backend indexes and bills a series for, while a field rides along in the same
 * record and stays queryable without multiplying anything. A granular attribute belongs in a
 * field.</p>
 *
 * <p>Rendering is a {@see MetricFormat} concern. This type never knows which backend reads it.</p>
 */
final readonly class Metric
{
    private function __construct(
        public MetricName $name,
        public MetricUnit $unit,
        public int|float $value,
        public array $fields,
        public MetricNamespace $namespace,
        public array $dimensions
    ) {
        $shared = array_key_first(array_intersect_key($fields, $dimensions));

        if (!is_null($shared)) {
            throw new DuplicateMetricIdentifier(identifier: (string)$shared);
        }
    }

    /**
     * Creates a metric counting one occurrence, with no dimension and no field.
     *
     * <p>A measurement that is not a count derives from here through {@see Metric::withUnit()} and
     * {@see Metric::withValue()}, so the only arguments a metric always takes are the two that
     * identify it.</p>
     *
     * @param string $name The metric name (e.g., OfferAccepted).
     * @param string $namespace The namespace the series belongs to (e.g., Acme/Waitlist).
     * @return Metric The created instance.
     * @throws BlankMetricIdentifier If the name or the namespace carries nothing.
     */
    public static function of(string $name, string $namespace): Metric
    {
        return new Metric(
            name: MetricName::from(value: $name),
            unit: MetricUnit::COUNT,
            value: 1,
            fields: [],
            namespace: MetricNamespace::from(value: $namespace),
            dimensions: []
        );
    }

    /**
     * Returns a copy of the metric carrying no field at all.
     *
     * <p>What survives a redaction is what the record should carry, so a caller that filters the
     * fields rebuilds them from an empty one instead of writing over the originals: a field the
     * filter dropped has no value to write, and would otherwise stay.</p>
     *
     * @return Metric A copy with every field removed.
     */
    public function withoutFields(): Metric
    {
        return new Metric(
            name: $this->name,
            unit: $this->unit,
            value: $this->value,
            fields: [],
            namespace: $this->namespace,
            dimensions: $this->dimensions
        );
    }

    /**
     * Returns a copy of the metric expressed in another unit.
     *
     * @param MetricUnit $unit The unit the value is expressed in.
     * @return Metric A copy with the unit set.
     */
    public function withUnit(MetricUnit $unit): Metric
    {
        return new Metric(
            name: $this->name,
            unit: $unit,
            value: $this->value,
            fields: $this->fields,
            namespace: $this->namespace,
            dimensions: $this->dimensions
        );
    }

    /**
     * Returns a copy of the metric carrying one more field in the same record.
     *
     * @param string $name The field name.
     * @param string|int|float $value The field value.
     * @return Metric A copy with the field set.
     * @throws BlankMetricIdentifier If the field name carries nothing.
     * @throws DuplicateMetricIdentifier If a dimension already carries that name.
     */
    public function withField(string $name, string|int|float $value): Metric
    {
        return new Metric(
            name: $this->name,
            unit: $this->unit,
            value: $this->value,
            fields: [...$this->fields, $name => MetricField::of(name: $name, value: $value)],
            namespace: $this->namespace,
            dimensions: $this->dimensions
        );
    }

    /**
     * Returns a copy of the metric with the measured value replaced.
     *
     * @param int|float $value The value the metric carries.
     * @return Metric A copy with the value set.
     */
    public function withValue(int|float $value): Metric
    {
        return new Metric(
            name: $this->name,
            unit: $this->unit,
            value: $value,
            fields: $this->fields,
            namespace: $this->namespace,
            dimensions: $this->dimensions
        );
    }

    /**
     * Returns a copy of the metric broken down by one more dimension.
     *
     * <p>Every distinct set of dimension values is a series of its own, so a dimension that takes an
     * unbounded number of values multiplies what the backend stores and bills. A format refuses the
     * names its consumer declares unbounded, and a granular attribute belongs in
     * {@see Metric::withField()} instead.</p>
     *
     * @param string $name The dimension name.
     * @param string $value The dimension value.
     * @return Metric A copy with the dimension set.
     * @throws BlankMetricIdentifier If the dimension name carries nothing.
     * @throws DuplicateMetricIdentifier If a field already carries that name.
     */
    public function withDimension(string $name, string $value): Metric
    {
        return new Metric(
            name: $this->name,
            unit: $this->unit,
            value: $this->value,
            fields: $this->fields,
            namespace: $this->namespace,
            dimensions: [...$this->dimensions, $name => MetricDimension::of(name: $name, value: $value)]
        );
    }
}

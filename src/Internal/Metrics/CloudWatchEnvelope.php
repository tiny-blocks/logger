<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Metrics;

use DateTimeImmutable;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricDimension;
use TinyBlocks\Logger\Metrics\MetricField;

final readonly class CloudWatchEnvelope
{
    private const int MILLISECONDS_PER_SECOND = 1000;

    public function __construct(private Metric $metric)
    {
    }

    public function toPayload(): array
    {
        $name = $this->metric->name->value;
        $timestamp = (new DateTimeImmutable()->getTimestamp() * self::MILLISECONDS_PER_SECOND);
        $fields = array_map(static fn(MetricField $field): string|int|float => $field->value, $this->metric->fields);
        $dimensions = array_map(
            static fn(MetricDimension $dimension): string => $dimension->value,
            $this->metric->dimensions
        );

        return array_merge(
            $fields,
            $dimensions,
            [$name => $this->metric->value],
            [
                '_aws' => [
                    'Timestamp'         => $timestamp,
                    'CloudWatchMetrics' => [
                        [
                            'Namespace'  => $this->metric->namespace->value,
                            'Dimensions' => [array_keys($dimensions)],
                            'Metrics'    => [
                                ['Name' => $name, 'Unit' => CloudWatchUnit::from(unit: $this->metric->unit)]
                            ]
                        ]
                    ]
                ]
            ]
        );
    }
}

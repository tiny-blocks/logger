<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Metrics;

use TinyBlocks\Logger\Exceptions\RedactedDimension;
use TinyBlocks\Logger\Internal\EncodedPayload;
use TinyBlocks\Logger\Internal\Redactor\Redactions;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricFormat;

final readonly class MetricLine
{
    private function __construct(private array $payload)
    {
    }

    public static function from(
        MetricFormat $format,
        Metric $metric,
        string $component,
        Redactions $redactions,
        string $correlationId
    ): MetricLine {
        $values = [];

        foreach ($metric->fields as $name => $field) {
            $values[$name] = $field->value;
        }

        $dimensions = [];

        foreach ($metric->dimensions as $name => $dimension) {
            $dimensions[$name] = $dimension->value;
        }

        $sensitive = array_diff_assoc($dimensions, $redactions->applyTo(payload: $dimensions));

        foreach (array_keys($sensitive) as $name) {
            throw new RedactedDimension(dimension: (string)$name);
        }

        $carried = $metric->withoutFields();

        foreach ($redactions->applyTo(payload: $values) as $name => $value) {
            $carried = $carried->withField(name: (string)$name, value: $value);
        }

        $identified = $carried
            ->withField(name: 'component', value: $component)
            ->withField(name: 'correlation_id', value: $correlationId);

        return new MetricLine(payload: $format->format(metric: $identified));
    }

    public function toString(): string
    {
        return sprintf('%s%s', EncodedPayload::from(payload: $this->payload)->toString(), PHP_EOL);
    }
}

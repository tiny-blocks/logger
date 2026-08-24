<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Metrics;

/**
 * The unit a metric value is expressed in, as a UCUM symbol.
 *
 * <p>Base units only, which is what the neutral specifications prescribe: OpenTelemetry asks for
 * non-prefixed units and for durations in seconds, and OpenMetrics asks for base units too. A
 * duration of half a second is `0.5` in {@see MetricUnit::SECONDS}, never `500` in a millisecond
 * unit that does not exist here. A backend that spells them its own way translates in its
 * {@see MetricFormat}, and every backend can express these six.</p>
 */
enum MetricUnit: string
{
    case BITS = 'bit';
    case NONE = '1';
    case BYTES = 'By';
    case COUNT = '{count}';
    case PERCENT = '%';
    case SECONDS = 's';
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Metrics;

use TinyBlocks\Logger\Metrics\MetricUnit;

final readonly class CloudWatchUnit
{
    private const array TOKENS = [
        'bit'     => 'Bits',
        '1'       => 'None',
        'By'      => 'Bytes',
        '{count}' => 'Count',
        '%'       => 'Percent',
        's'       => 'Seconds'
    ];

    public static function from(MetricUnit $unit): string
    {
        return self::TOKENS[$unit->value];
    }
}

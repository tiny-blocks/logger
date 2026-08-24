<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger\Models;

final readonly class EmbeddedMetricPayload
{
    private function __construct(private array $payload)
    {
    }

    public static function from(array $payload): EmbeddedMetricPayload
    {
        return new EmbeddedMetricPayload(payload: $payload);
    }

    public function unit(): mixed
    {
        return $this->declaration()['Metrics'][0]['Unit'];
    }

    public function metrics(): mixed
    {
        return $this->declaration()['Metrics'];
    }

    public function timestamp(): mixed
    {
        return $this->envelope()['Timestamp'];
    }

    public function namespaced(): mixed
    {
        return $this->declaration()['Namespace'];
    }

    public function dimensions(): mixed
    {
        return $this->declaration()['Dimensions'];
    }

    private function envelope(): array
    {
        return (array)$this->payload['_aws'];
    }

    private function declaration(): array
    {
        $declarations = (array)$this->envelope()['CloudWatchMetrics'];

        return (array)$declarations[0];
    }
}

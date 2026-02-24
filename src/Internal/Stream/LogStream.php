<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Stream;

final readonly class LogStream
{
    /** @var resource */
    private mixed $resource;

    private function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    public static function from(mixed $resource = null): LogStream
    {
        if ($resource !== null) {
            return new LogStream(resource: $resource);
        }

        $fallback = fopen('php://stderr', 'wb');

        return new LogStream(resource: $fallback);
    }

    public function write(string $content): void
    {
        fwrite($this->resource, $content);
    }
}

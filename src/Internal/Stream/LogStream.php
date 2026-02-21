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
        return new LogStream(resource: $resource ?? STDERR);
    }

    public function write(string $content): void
    {
        fwrite($this->resource, $content);
    }
}

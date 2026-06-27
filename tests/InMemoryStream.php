<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger;

final class InMemoryStream
{
    private function __construct(private mixed $resource)
    {
    }

    public static function create(): InMemoryStream
    {
        return new InMemoryStream(resource: fopen('php://memory', 'r+'));
    }

    public function close(): void
    {
        $resource = $this->resource;

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function handle(): mixed
    {
        return $this->resource;
    }

    public function contents(): string
    {
        $resource = $this->resource;

        if (!is_resource($resource)) {
            return '';
        }

        rewind($resource);

        return (string)stream_get_contents($resource);
    }
}

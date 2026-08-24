<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Metrics;

final readonly class UnboundedDimensions
{
    private function __construct(private array $names)
    {
    }

    public static function none(): UnboundedDimensions
    {
        return new UnboundedDimensions(names: []);
    }

    public function and(string ...$names): UnboundedDimensions
    {
        return new UnboundedDimensions(names: array_merge($this->names, $names));
    }

    public function firstIn(array $dimensions): ?string
    {
        return array_find(
            $this->names,
            static fn(mixed $name): bool => array_key_exists($name, $dimensions)
        );
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

final readonly class VisibleWords
{
    public function __construct(private VisibleEdges $edges)
    {
    }

    public function applyTo(string $value): string
    {
        $words = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map($this->edges->applyTo(...), $words));
    }
}

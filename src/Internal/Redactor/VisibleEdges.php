<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Mask;

final readonly class VisibleEdges
{
    public function __construct(private Mask $mask, private int $prefixLength, private int $suffixLength)
    {
        if (min($prefixLength, $suffixLength) < 0) {
            $template = 'Visible length cannot be negative, got prefix %d and suffix %d.';

            throw new NegativeVisibleLength(message: sprintf($template, $prefixLength, $suffixLength));
        }
    }

    public function applyTo(string $value): string
    {
        $totalLength = mb_strlen($value, 'UTF-8');
        $hiddenLength = max(0, ($totalLength - $this->prefixLength - $this->suffixLength));
        $hidden = mb_substr($value, $this->prefixLength, $hiddenLength, 'UTF-8');
        $template = '%s%s%s';

        return sprintf(
            $template,
            mb_substr($value, 0, $this->prefixLength, 'UTF-8'),
            $this->mask->applyTo(hidden: $hidden),
            mb_substr($value, ($this->prefixLength + $hiddenLength), null, 'UTF-8')
        );
    }
}

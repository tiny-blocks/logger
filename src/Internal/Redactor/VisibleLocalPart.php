<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Logger\Mask;

final readonly class VisibleLocalPart
{
    public function __construct(private Mask $mask, private VisibleEdges $localPart)
    {
    }

    public function applyTo(string $value): string
    {
        $atPosition = mb_strpos($value, '@', 0, 'UTF-8');

        if ($atPosition === false) {
            return $this->mask->applyTo(hidden: $value);
        }

        $template = '%s%s';

        return sprintf(
            $template,
            $this->localPart->applyTo(value: mb_substr($value, 0, $atPosition, 'UTF-8')),
            mb_substr($value, $atPosition, null, 'UTF-8')
        );
    }
}

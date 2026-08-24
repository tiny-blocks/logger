<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Logger\Redactions\Mask;

enum VisibleShape: string
{
    case EDGES = 'EDGES';
    case WORDS = 'WORDS';
    case LOCAL_PART = 'LOCAL_PART';

    public function applyTo(Mask $mask, string $value, int $prefixLength, int $suffixLength): string
    {
        return match ($this) {
            VisibleShape::EDGES      => VisibleShape::edges(
                mask: $mask,
                value: $value,
                prefixLength: $prefixLength,
                suffixLength: $suffixLength
            ),
            VisibleShape::WORDS      => VisibleShape::words(
                mask: $mask,
                value: $value,
                prefixLength: $prefixLength,
                suffixLength: $suffixLength
            ),
            VisibleShape::LOCAL_PART => VisibleShape::localPart(
                mask: $mask,
                value: $value,
                prefixLength: $prefixLength,
                suffixLength: $suffixLength
            )
        };
    }

    private static function edges(Mask $mask, string $value, int $prefixLength, int $suffixLength): string
    {
        $totalLength = mb_strlen($value, 'UTF-8');
        $hiddenLength = max(0, ($totalLength - $prefixLength - $suffixLength));
        $hidden = mb_substr($value, $prefixLength, $hiddenLength, 'UTF-8');
        $template = '%s%s%s';

        return sprintf(
            $template,
            mb_substr($value, 0, $prefixLength, 'UTF-8'),
            $mask->applyTo(hidden: $hidden),
            mb_substr($value, ($prefixLength + $hiddenLength), null, 'UTF-8')
        );
    }

    private static function words(Mask $mask, string $value, int $prefixLength, int $suffixLength): string
    {
        $words = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $masked = array_map(
            static fn(string $word): string => VisibleShape::edges(
                mask: $mask,
                value: $word,
                prefixLength: $prefixLength,
                suffixLength: $suffixLength
            ),
            $words
        );

        return implode(' ', $masked);
    }

    private static function localPart(Mask $mask, string $value, int $prefixLength, int $suffixLength): string
    {
        $atPosition = mb_strpos($value, '@', 0, 'UTF-8');

        if ($atPosition === false) {
            return $mask->applyTo(hidden: $value);
        }

        $template = '%s%s';

        return sprintf(
            $template,
            VisibleShape::edges(
                mask: $mask,
                value: mb_substr($value, 0, $atPosition, 'UTF-8'),
                prefixLength: $prefixLength,
                suffixLength: $suffixLength
            ),
            mb_substr($value, $atPosition, null, 'UTF-8')
        );
    }
}

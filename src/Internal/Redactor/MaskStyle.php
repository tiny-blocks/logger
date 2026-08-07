<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

enum MaskStyle: string
{
    private const string MASK_CHARACTER = '*';

    case FIXED = 'FIXED';
    case PROPORTIONAL = 'PROPORTIONAL';
    case PRESERVING_SEPARATORS = 'PRESERVING_SEPARATORS';

    public static function fixedMaskOf(int $length): string
    {
        return str_repeat(self::MASK_CHARACTER, $length);
    }

    private static function withoutLettersAndDigits(string $value): string
    {
        $masked = array_map(
            static fn(string $character): string => preg_match('/[\p{L}\p{N}]/u', $character) === 1
                ? self::MASK_CHARACTER
                : $character,
            mb_str_split($value, 1, 'UTF-8')
        );

        return implode('', $masked);
    }

    public function applyTo(string $hidden, string $fixedMask): string
    {
        return match ($this) {
            self::FIXED                 => $fixedMask,
            self::PROPORTIONAL          => str_repeat(self::MASK_CHARACTER, mb_strlen($hidden, 'UTF-8')),
            self::PRESERVING_SEPARATORS => MaskStyle::withoutLettersAndDigits(value: $hidden)
        };
    }
}

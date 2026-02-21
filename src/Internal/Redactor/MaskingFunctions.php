<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use Closure;

final readonly class MaskingFunctions
{
    private const int ZERO = 0;

    public static function lastNVisible(int $visibleChars): Closure
    {
        return static function (string $value) use ($visibleChars): string {
            $maskedLength = max(self::ZERO, strlen($value) - $visibleChars);
            $maskedPart = str_repeat('*', $maskedLength);
            $visiblePart = substr($value, -$visibleChars);

            return sprintf('%s%s', $maskedPart, $visiblePart);
        };
    }

    public static function full(string $replacement = '***REDACTED***'): Closure
    {
        return static fn(string $value): string => $replacement;
    }

    public static function firstNVisible(int $visibleChars): Closure
    {
        return static function (string $value) use ($visibleChars): string {
            $visiblePart = substr($value, self::ZERO, $visibleChars);
            $maskedLength = max(self::ZERO, strlen($value) - $visibleChars);
            $maskedPart = str_repeat('*', $maskedLength);

            return sprintf('%s%s', $visiblePart, $maskedPart);
        };
    }
}

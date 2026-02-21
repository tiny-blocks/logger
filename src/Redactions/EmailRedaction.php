<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

final readonly class EmailRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 2;

    private Redactor $redactor;

    private function __construct(array $fields, int $visiblePrefixLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static function (string $value) use ($visiblePrefixLength): string {
                $atPosition = strpos($value, '@');

                if ($atPosition === false) {
                    return str_repeat('*', strlen($value));
                }

                $localPart = substr($value, 0, $atPosition);
                $domain = substr($value, $atPosition);
                $visiblePrefix = substr($localPart, 0, $visiblePrefixLength);
                $maskedSuffix = str_repeat('*', max(0, strlen($localPart) - $visiblePrefixLength));

                return sprintf('%s%s%s', $visiblePrefix, $maskedSuffix, $domain);
            }
        );
    }

    public static function from(array $fields, int $visiblePrefixLength): EmailRedaction
    {
        return new EmailRedaction(fields: $fields, visiblePrefixLength: $visiblePrefixLength);
    }

    public static function default(): EmailRedaction
    {
        return self::from(fields: ['email'], visiblePrefixLength: self::DEFAULT_VISIBLE_PREFIX_LENGTH);
    }

    public function redact(array $data): array
    {
        return $this->redactor->redact(data: $data);
    }
}

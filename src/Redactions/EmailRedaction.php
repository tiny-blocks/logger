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
                $atPosition = mb_strpos($value, '@', 0, 'UTF-8');

                if ($atPosition === false) {
                    return str_repeat('*', mb_strlen($value, 'UTF-8'));
                }

                $domain = mb_substr($value, $atPosition, null, 'UTF-8');
                $localPart = mb_substr($value, 0, $atPosition, 'UTF-8');
                $maskedSuffix = str_repeat('*', max(0, mb_strlen($localPart, 'UTF-8') - $visiblePrefixLength));
                $visiblePrefix = mb_substr($localPart, 0, $visiblePrefixLength, 'UTF-8');

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

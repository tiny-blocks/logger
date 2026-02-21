<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

final readonly class PhoneRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_SUFFIX_LENGTH = 4;

    private Redactor $redactor;

    private function __construct(array $fields, int $visibleSuffixLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static function (string $value) use ($visibleSuffixLength): string {
                $maskedLength = max(0, strlen($value) - $visibleSuffixLength);
                return sprintf('%s%s', str_repeat('*', $maskedLength), substr($value, -$visibleSuffixLength));
            }
        );
    }

    public static function from(array $fields, int $visibleSuffixLength): PhoneRedaction
    {
        return new PhoneRedaction(fields: $fields, visibleSuffixLength: $visibleSuffixLength);
    }

    public static function default(): PhoneRedaction
    {
        return self::from(fields: ['phone'], visibleSuffixLength: self::DEFAULT_VISIBLE_SUFFIX_LENGTH);
    }

    public function redact(array $data): array
    {
        return $this->redactor->redact(data: $data);
    }
}

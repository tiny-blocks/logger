<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

final readonly class NameRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 2;

    private Redactor $redactor;

    private function __construct(array $fields, int $visiblePrefixLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static function (string $value) use ($visiblePrefixLength): string {
                $maskedLength = max(0, mb_strlen($value, 'UTF-8') - $visiblePrefixLength);
                return sprintf(
                    '%s%s',
                    mb_substr($value, 0, $visiblePrefixLength, 'UTF-8'),
                    str_repeat('*', $maskedLength)
                );
            }
        );
    }

    public static function from(array $fields, int $visiblePrefixLength): NameRedaction
    {
        return new NameRedaction(fields: $fields, visiblePrefixLength: $visiblePrefixLength);
    }

    public static function default(): NameRedaction
    {
        return self::from(fields: ['name'], visiblePrefixLength: self::DEFAULT_VISIBLE_PREFIX_LENGTH);
    }

    public function redact(array $data): array
    {
        return $this->redactor->redact(data: $data);
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

/**
 * Masks phone field values, keeping a configurable number of trailing characters visible.
 */
final readonly class PhoneRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_SUFFIX_LENGTH = 4;

    private Redactor $redactor;

    private function __construct(array $fields, int $visibleSuffixLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static function (string $value) use ($visibleSuffixLength): string {
                $length = mb_strlen($value, 'UTF-8');
                $maskedLength = max(0, $length - $visibleSuffixLength);
                $template = '%s%s';

                return sprintf(
                    $template,
                    str_repeat('*', $maskedLength),
                    mb_substr($value, -$visibleSuffixLength, null, 'UTF-8')
                );
            }
        );
    }

    /**
     * Creates a PhoneRedaction from the fields to mask and the number of visible trailing characters.
     *
     * @param string[] $fields The field names whose values are masked.
     * @param int $visibleSuffixLength The number of trailing characters left visible.
     * @return PhoneRedaction The created instance.
     */
    public static function from(array $fields, int $visibleSuffixLength): PhoneRedaction
    {
        return new PhoneRedaction(fields: $fields, visibleSuffixLength: $visibleSuffixLength);
    }

    /**
     * Builds a PhoneRedaction with the default phone field and visible suffix length.
     *
     * @return PhoneRedaction The created instance.
     */
    public static function default(): PhoneRedaction
    {
        return PhoneRedaction::from(fields: ['phone'], visibleSuffixLength: self::DEFAULT_VISIBLE_SUFFIX_LENGTH);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

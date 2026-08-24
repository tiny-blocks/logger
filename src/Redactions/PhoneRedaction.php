<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;

/**
 * Masks phone field values, keeping a configurable number of trailing characters visible.
 */
final readonly class PhoneRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_SUFFIX_LENGTH = 4;

    private Redaction $redactor;

    private function __construct(array $fields, int $visibleSuffixLength)
    {
        $this->redactor = GenericRedaction::masking(
            mask: Mask::proportional(),
            fields: $fields,
            visibility: Visibility::edges(suffixLength: $visibleSuffixLength)
        );
    }

    /**
     * Creates a PhoneRedaction from the fields to mask and the number of visible trailing characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int|null $visibleSuffixLength Trailing characters left visible, or null for the default.
     * @return PhoneRedaction The created instance.
     * @throws NegativeVisibleLength If the visible suffix length is negative.
     */
    public static function from(array $fields, ?int $visibleSuffixLength = null): PhoneRedaction
    {
        return new PhoneRedaction(
            fields: $fields,
            visibleSuffixLength: ($visibleSuffixLength ?? self::DEFAULT_VISIBLE_SUFFIX_LENGTH)
        );
    }

    /**
     * Builds a PhoneRedaction with the default phone field and visible suffix length.
     *
     * @return PhoneRedaction The created instance.
     */
    public static function default(): PhoneRedaction
    {
        return PhoneRedaction::from(fields: ['phone']);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;
use TinyBlocks\Logger\Redactions\Rules\VisibleEdgesRedaction;

/**
 * Masks phone field values, keeping a configurable number of trailing characters visible.
 */
final readonly class PhoneRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_SUFFIX_LENGTH = 4;

    private Redaction $redactor;

    private function __construct(array $fields, int $visibleSuffixLength)
    {
        $this->redactor = VisibleEdgesRedaction::from(
            mask: Mask::proportional(),
            fields: $fields,
            visibleSuffixLength: $visibleSuffixLength
        );
    }

    /**
     * Creates a PhoneRedaction from the fields to mask and the number of visible trailing characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int $visibleSuffixLength The number of trailing characters left visible.
     * @return PhoneRedaction The created instance.
     * @throws NegativeVisibleLength If the visible suffix length is negative.
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

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;
use TinyBlocks\Logger\Redactions\Rules\FullMaskRedaction;

/**
 * Masks password field values entirely with a fixed-length mask.
 */
final readonly class PasswordRedaction implements Redaction
{
    private const int DEFAULT_FIXED_MASK_LENGTH = 8;

    private Redaction $redactor;

    private function __construct(array $fields, int $fixedMaskLength)
    {
        $this->redactor = FullMaskRedaction::from(mask: Mask::fixed(length: $fixedMaskLength), fields: $fields);
    }

    /**
     * Creates a PasswordRedaction from the fields to mask and the fixed mask length.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int $fixedMaskLength The fixed number of mask characters emitted.
     * @return PasswordRedaction The created instance.
     */
    public static function from(
        array $fields,
        int $fixedMaskLength = self::DEFAULT_FIXED_MASK_LENGTH
    ): PasswordRedaction {
        return new PasswordRedaction(fields: $fields, fixedMaskLength: $fixedMaskLength);
    }

    /**
     * Builds a PasswordRedaction with the default password field and fixed mask length.
     *
     * @return PasswordRedaction The created instance.
     */
    public static function default(): PasswordRedaction
    {
        return PasswordRedaction::from(fields: ['password']);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

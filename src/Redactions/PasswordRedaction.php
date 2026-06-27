<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

/**
 * Masks password field values entirely with a fixed-length mask.
 */
final readonly class PasswordRedaction implements Redaction
{
    private const int DEFAULT_FIXED_MASK_LENGTH = 8;

    private Redactor $redactor;

    private function __construct(array $fields, int $fixedMaskLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static fn(): string => str_repeat('*', $fixedMaskLength)
        );
    }

    /**
     * Creates a PasswordRedaction from the fields to mask and the fixed mask length.
     *
     * @param string[] $fields The field names whose values are masked.
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

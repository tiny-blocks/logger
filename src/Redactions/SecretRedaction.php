<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

/**
 * Masks credential field values entirely, leaving neither the value nor its length visible.
 *
 * <p>A secret carries no operational meaning once logged, and its length is itself a clue, so the
 * mask is fixed. The default field list follows the naming conventions credentials travel under
 * rather than a fixed set of names, which is what covers the field nobody remembered to declare.</p>
 */
final readonly class SecretRedaction implements Redaction
{
    private const int DEFAULT_FIXED_MASK_LENGTH = 8;

    private const array DEFAULT_FIELDS = [
        '*token*',
        '*secret*',
        '*api_key*',
        '*password*',
        'credentials',
        'authorization',
        '*private_key*'
    ];

    private Redaction $redactor;

    private function __construct(array $fields, int $fixedMaskLength)
    {
        $this->redactor = GenericRedaction::masking(mask: Mask::fixed(length: $fixedMaskLength), fields: $fields);
    }

    /**
     * Creates a SecretRedaction from the fields to mask and the length of the fixed mask.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int $fixedMaskLength The number of mask characters emitted for every value.
     * @return SecretRedaction The created instance.
     */
    public static function from(array $fields, int $fixedMaskLength = self::DEFAULT_FIXED_MASK_LENGTH): SecretRedaction
    {
        return new SecretRedaction(fields: $fields, fixedMaskLength: $fixedMaskLength);
    }

    /**
     * Builds a SecretRedaction covering the field name patterns credentials commonly travel under.
     *
     * @return SecretRedaction The created instance.
     */
    public static function default(): SecretRedaction
    {
        return SecretRedaction::from(fields: self::DEFAULT_FIELDS);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

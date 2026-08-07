<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions\Rules;

use TinyBlocks\Logger\Internal\Redactor\FieldMatcher;
use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;

/**
 * Masks field values entirely, leaving no part of the original value visible.
 *
 * <p>The strategy for values that carry no operational meaning once logged, such as secrets, free
 * text, network addresses, and user agents.</p>
 */
final readonly class FullMaskRedaction implements Redaction
{
    private const int SECRET_MASK_LENGTH = 8;

    private const array COMMON_SECRET_FIELDS = [
        '*token*',
        '*secret*',
        '*api_key*',
        '*password*',
        'credentials',
        'authorization',
        '*private_key*'
    ];

    private Redaction $redactor;

    private function __construct(Mask $mask, array $fields)
    {
        $this->redactor = new Redactor(
            fields: new FieldMatcher(fields: $fields),
            maskingFunction: $mask->applyTo(...)
        );
    }

    /**
     * Creates a FullMaskRedaction from the mask and the fields to redact.
     *
     * @param Mask $mask The strategy rendering the masked value.
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @return FullMaskRedaction The created instance.
     */
    public static function from(Mask $mask, array $fields): FullMaskRedaction
    {
        return new FullMaskRedaction(mask: $mask, fields: $fields);
    }

    /**
     * Builds a FullMaskRedaction covering the field names that carry secrets across most systems.
     *
     * <p>Matches every field whose name contains <code>token</code>, <code>secret</code>,
     * <code>api_key</code>, <code>password</code>, or <code>private_key</code>, plus
     * <code>credentials</code> and <code>authorization</code>. The mask has a fixed width, so the
     * length of the secret is never revealed.</p>
     *
     * @return FullMaskRedaction The created instance.
     */
    public static function commonSecrets(): FullMaskRedaction
    {
        return FullMaskRedaction::from(
            mask: Mask::fixed(length: self::SECRET_MASK_LENGTH),
            fields: self::COMMON_SECRET_FIELDS
        );
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;

/**
 * Masks the local part of email field values, keeping a configurable visible prefix and the domain.
 */
final readonly class EmailRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 2;

    private Redaction $redactor;

    private function __construct(array $fields, int $visiblePrefixLength)
    {
        $this->redactor = GenericRedaction::masking(
            mask: Mask::proportional(),
            fields: $fields,
            visibility: Visibility::localPart(prefixLength: $visiblePrefixLength)
        );
    }

    /**
     * Creates an EmailRedaction from the fields to mask and the number of visible leading characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int|null $visiblePrefixLength Leading characters of the local part visible, or null for the default.
     * @return EmailRedaction The created instance.
     * @throws NegativeVisibleLength If the visible prefix length is negative.
     */
    public static function from(array $fields, ?int $visiblePrefixLength = null): EmailRedaction
    {
        return new EmailRedaction(
            fields: $fields,
            visiblePrefixLength: ($visiblePrefixLength ?? self::DEFAULT_VISIBLE_PREFIX_LENGTH)
        );
    }

    /**
     * Builds an EmailRedaction with the default email field and visible prefix length.
     *
     * @return EmailRedaction The created instance.
     */
    public static function default(): EmailRedaction
    {
        return EmailRedaction::from(fields: ['email']);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

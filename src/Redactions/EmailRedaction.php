<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Internal\Redactor\FieldMatcher;
use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Internal\Redactor\VisibleEdges;
use TinyBlocks\Logger\Internal\Redactor\VisibleLocalPart;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;

/**
 * Masks the local part of email field values, keeping a configurable visible prefix and the domain.
 */
final readonly class EmailRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 2;

    private Redaction $redactor;

    private function __construct(array $fields, int $visiblePrefixLength)
    {
        $mask = Mask::proportional();
        $localPart = new VisibleLocalPart(
            mask: $mask,
            localPart: new VisibleEdges(mask: $mask, prefixLength: $visiblePrefixLength, suffixLength: 0)
        );

        $this->redactor = new Redactor(
            fields: new FieldMatcher(fields: $fields),
            maskingFunction: $localPart->applyTo(...)
        );
    }

    /**
     * Creates an EmailRedaction from the fields to mask and the number of visible leading characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int $visiblePrefixLength The number of leading characters of the local part left visible.
     * @return EmailRedaction The created instance.
     * @throws NegativeVisibleLength If the visible prefix length is negative.
     */
    public static function from(array $fields, int $visiblePrefixLength): EmailRedaction
    {
        return new EmailRedaction(fields: $fields, visiblePrefixLength: $visiblePrefixLength);
    }

    /**
     * Builds an EmailRedaction with the default email field and visible prefix length.
     *
     * @return EmailRedaction The created instance.
     */
    public static function default(): EmailRedaction
    {
        return EmailRedaction::from(fields: ['email'], visiblePrefixLength: self::DEFAULT_VISIBLE_PREFIX_LENGTH);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

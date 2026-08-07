<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;
use TinyBlocks\Logger\Redactions\Rules\VisibleEdgesRedaction;

/**
 * Masks name field values, keeping a configurable number of leading characters visible.
 */
final readonly class NameRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 2;

    private Redaction $redactor;

    private function __construct(array $fields, int $visiblePrefixLength)
    {
        $this->redactor = VisibleEdgesRedaction::from(
            mask: Mask::proportional(),
            fields: $fields,
            visiblePrefixLength: $visiblePrefixLength
        );
    }

    /**
     * Creates a NameRedaction from the fields to mask and the number of visible leading characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int $visiblePrefixLength The number of leading characters left visible.
     * @return NameRedaction The created instance.
     * @throws NegativeVisibleLength If the visible prefix length is negative.
     */
    public static function from(array $fields, int $visiblePrefixLength): NameRedaction
    {
        return new NameRedaction(fields: $fields, visiblePrefixLength: $visiblePrefixLength);
    }

    /**
     * Builds a NameRedaction with the default name field and visible prefix length.
     *
     * @return NameRedaction The created instance.
     */
    public static function default(): NameRedaction
    {
        return NameRedaction::from(fields: ['name'], visiblePrefixLength: self::DEFAULT_VISIBLE_PREFIX_LENGTH);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

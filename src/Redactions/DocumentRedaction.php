<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;
use TinyBlocks\Logger\Redactions\Rules\VisibleEdgesRedaction;

/**
 * Masks document field values, keeping a configurable number of trailing characters visible.
 */
final readonly class DocumentRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_SUFFIX_LENGTH = 3;

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
     * Creates a DocumentRedaction from the fields to mask and the number of visible trailing characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int $visibleSuffixLength The number of trailing characters left visible.
     * @return DocumentRedaction The created instance.
     * @throws NegativeVisibleLength If the visible suffix length is negative.
     */
    public static function from(array $fields, int $visibleSuffixLength): DocumentRedaction
    {
        return new DocumentRedaction(fields: $fields, visibleSuffixLength: $visibleSuffixLength);
    }

    /**
     * Builds a DocumentRedaction with the default document field and visible suffix length.
     *
     * @return DocumentRedaction The created instance.
     */
    public static function default(): DocumentRedaction
    {
        return DocumentRedaction::from(fields: ['document'], visibleSuffixLength: self::DEFAULT_VISIBLE_SUFFIX_LENGTH);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;

/**
 * Masks document field values, keeping a configurable number of trailing characters visible.
 */
final readonly class DocumentRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_SUFFIX_LENGTH = 2;

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
     * Creates a DocumentRedaction from the fields to mask and the number of visible trailing characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int|null $visibleSuffixLength Trailing characters left visible, or null for the default.
     * @return DocumentRedaction The created instance.
     * @throws NegativeVisibleLength If the visible suffix length is negative.
     */
    public static function from(array $fields, ?int $visibleSuffixLength = null): DocumentRedaction
    {
        return new DocumentRedaction(
            fields: $fields,
            visibleSuffixLength: ($visibleSuffixLength ?? self::DEFAULT_VISIBLE_SUFFIX_LENGTH)
        );
    }

    /**
     * Builds a DocumentRedaction with the default document field and visible suffix length.
     *
     * @return DocumentRedaction The created instance.
     */
    public static function default(): DocumentRedaction
    {
        return DocumentRedaction::from(fields: ['document']);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

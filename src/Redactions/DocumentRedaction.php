<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

/**
 * Masks document field values, keeping a configurable number of trailing characters visible.
 */
final readonly class DocumentRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_SUFFIX_LENGTH = 3;

    private Redactor $redactor;

    private function __construct(array $fields, int $visibleSuffixLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static function (string $value) use ($visibleSuffixLength): string {
                $length = mb_strlen($value, 'UTF-8');
                $maskedLength = max(0, $length - $visibleSuffixLength);
                $template = '%s%s';

                return sprintf(
                    $template,
                    str_repeat('*', $maskedLength),
                    mb_substr($value, -$visibleSuffixLength, null, 'UTF-8')
                );
            }
        );
    }

    /**
     * Creates a DocumentRedaction from the fields to mask and the number of visible trailing characters.
     *
     * @param string[] $fields The field names whose values are masked.
     * @param int $visibleSuffixLength The number of trailing characters left visible.
     * @return DocumentRedaction The created instance.
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

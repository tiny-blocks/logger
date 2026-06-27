<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

/**
 * Masks name field values, keeping a configurable number of leading characters visible.
 */
final readonly class NameRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 2;

    private Redactor $redactor;

    private function __construct(array $fields, int $visiblePrefixLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static function (string $value) use ($visiblePrefixLength): string {
                $maskedLength = max(0, mb_strlen($value, 'UTF-8') - $visiblePrefixLength);
                $template = '%s%s';

                return sprintf(
                    $template,
                    mb_substr($value, 0, $visiblePrefixLength, 'UTF-8'),
                    str_repeat('*', $maskedLength)
                );
            }
        );
    }

    /**
     * Creates a NameRedaction from the fields to mask and the number of visible leading characters.
     *
     * @param string[] $fields The field names whose values are masked.
     * @param int $visiblePrefixLength The number of leading characters left visible.
     * @return NameRedaction The created instance.
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

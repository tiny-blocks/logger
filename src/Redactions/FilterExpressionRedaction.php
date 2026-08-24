<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

/**
 * Masks the operand of every comparison in a filter expression, keeping field and operator visible.
 *
 * <p>A filter arrives as one string, so the field names inside it are out of reach of any field
 * based strategy, and the operand is where the sensitive value sits. Written for the comparison
 * syntax RSQL and FIQL share, where <code>status==active</code> and <code>document=in=(...)</code>
 * are the shapes a query takes, including the parenthesized list of an <code>=in=</code> comparison.
 * What is asked stays readable, what is asked about does not.</p>
 */
final readonly class FilterExpressionRedaction implements Redaction
{
    private const string PATTERN = '/([^;,()=!<>\s]+(?:==|!=|=[a-z]{2,4}=))(\([^)]*\)|[^;,()\s]*)/i';

    private const string REPLACEMENT = '${1}********';

    private Redaction $redactor;

    private function __construct()
    {
        $this->redactor = GenericRedaction::replacing(pattern: self::PATTERN, replacement: self::REPLACEMENT);
    }

    /**
     * Builds a FilterExpressionRedaction masking the operand of every comparison it finds.
     *
     * @return FilterExpressionRedaction The created instance.
     */
    public static function default(): FilterExpressionRedaction
    {
        return new FilterExpressionRedaction();
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

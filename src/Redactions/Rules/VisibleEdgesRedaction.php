<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions\Rules;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Internal\Redactor\FieldMatcher;
use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Internal\Redactor\VisibleEdges;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;

/**
 * Masks field values while keeping a configurable number of leading and trailing characters visible.
 *
 * <p>The general primitive behind the field-specific strategies. Use it whenever a value must keep
 * part of its head, part of its tail, or both, and the domain strategies do not fit.</p>
 */
final readonly class VisibleEdgesRedaction implements Redaction
{
    private Redaction $redactor;

    private function __construct(Mask $mask, array $fields, int $visiblePrefixLength, int $visibleSuffixLength)
    {
        $edges = new VisibleEdges(
            mask: $mask,
            prefixLength: $visiblePrefixLength,
            suffixLength: $visibleSuffixLength
        );

        $this->redactor = new Redactor(
            fields: new FieldMatcher(fields: $fields),
            maskingFunction: $edges->applyTo(...)
        );
    }

    /**
     * Creates a VisibleEdgesRedaction from the mask, the fields to redact, and the visible edges.
     *
     * @param Mask $mask The strategy rendering the hidden portion of each value.
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int $visiblePrefixLength The number of leading characters left visible.
     * @param int $visibleSuffixLength The number of trailing characters left visible.
     * @return VisibleEdgesRedaction The created instance.
     * @throws NegativeVisibleLength If either visible length is negative.
     */
    public static function from(
        Mask $mask,
        array $fields,
        int $visiblePrefixLength = 0,
        int $visibleSuffixLength = 0
    ): VisibleEdgesRedaction {
        return new VisibleEdgesRedaction(
            mask: $mask,
            fields: $fields,
            visiblePrefixLength: $visiblePrefixLength,
            visibleSuffixLength: $visibleSuffixLength
        );
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

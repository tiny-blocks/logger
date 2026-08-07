<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions\Rules;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Internal\Redactor\FieldMatcher;
use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Internal\Redactor\VisibleEdges;
use TinyBlocks\Logger\Internal\Redactor\VisibleWords;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;

/**
 * Masks each word of a field value independently, keeping the visible edges of every word.
 *
 * <p>Suited to values made of several parts, such as a full name, where masking the value as a
 * single run collapses it into an unreadable line. Words are separated by whitespace and rejoined
 * with a single space.</p>
 */
final readonly class WordwiseRedaction implements Redaction
{
    private Redaction $redactor;

    private function __construct(Mask $mask, array $fields, int $visiblePrefixLength, int $visibleSuffixLength)
    {
        $words = new VisibleWords(
            edges: new VisibleEdges(
                mask: $mask,
                prefixLength: $visiblePrefixLength,
                suffixLength: $visibleSuffixLength
            )
        );

        $this->redactor = new Redactor(
            fields: new FieldMatcher(fields: $fields),
            maskingFunction: $words->applyTo(...)
        );
    }

    /**
     * Creates a WordwiseRedaction from the mask, the fields to redact, and the visible edges.
     *
     * @param Mask $mask The strategy rendering the hidden portion of each word.
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int $visiblePrefixLength The number of leading characters of each word left visible.
     * @param int $visibleSuffixLength The number of trailing characters of each word left visible.
     * @return WordwiseRedaction The created instance.
     * @throws NegativeVisibleLength If either visible length is negative.
     */
    public static function from(
        Mask $mask,
        array $fields,
        int $visiblePrefixLength = 0,
        int $visibleSuffixLength = 0
    ): WordwiseRedaction {
        return new WordwiseRedaction(
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

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions\Rules;

use TinyBlocks\Logger\Exceptions\InvalidRedactionPattern;
use TinyBlocks\Logger\Internal\Redactor\PatternRedactor;
use TinyBlocks\Logger\Redaction;

/**
 * Masks every occurrence of a pattern inside string values, whatever their field name.
 *
 * <p>Field-based strategies cannot reach sensitive data embedded in free text: an exception message
 * quoting a document, a stack trace, a URI carrying a query string. Matching on the value instead of
 * the key closes that gap.</p>
 */
final readonly class PatternRedaction implements Redaction
{
    private Redaction $redactor;

    private function __construct(string $pattern, string $replacement)
    {
        $this->redactor = new PatternRedactor(pattern: $pattern, replacement: $replacement);
    }

    /**
     * Creates a PatternRedaction from the pattern to match and the text replacing every match.
     *
     * @param string $pattern The regular expression matched against every string value.
     * @param string $replacement The text every match is replaced with.
     * @return PatternRedaction The created instance.
     * @throws InvalidRedactionPattern If the regular expression engine rejects the pattern.
     */
    public static function from(string $pattern, string $replacement): PatternRedaction
    {
        return new PatternRedaction(pattern: $pattern, replacement: $replacement);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

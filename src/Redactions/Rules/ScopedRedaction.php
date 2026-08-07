<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions\Rules;

use TinyBlocks\Logger\Internal\Redactor\FieldMatcher;
use TinyBlocks\Logger\Internal\Redactor\ScopedRedactor;
use TinyBlocks\Logger\Redaction;

/**
 * Restricts another redaction to the sub payloads found under a given parent field.
 *
 * <p>Field names repeat across a payload with different meanings. A <code>value</code> under
 * <code>document</code> is an identity document, a <code>value</code> under <code>metadata</code>
 * is an operational marker. Scoping a redaction to its parent keeps the first masked and the second
 * readable.</p>
 */
final readonly class ScopedRedaction implements Redaction
{
    private Redaction $redactor;

    private function __construct(string $parent, Redaction $redaction)
    {
        $this->redactor = new ScopedRedactor(
            scope: new FieldMatcher(fields: [$parent]),
            redaction: $redaction
        );
    }

    /**
     * Creates a ScopedRedaction applying the given redaction only under the given parent field.
     *
     * @param string $parent The field name whose sub payloads the redaction is restricted to,
     *                       wildcards accepted.
     * @param Redaction $redaction The redaction applied within that scope.
     * @return ScopedRedaction The created instance.
     */
    public static function under(string $parent, Redaction $redaction): ScopedRedaction
    {
        return new ScopedRedaction(parent: $parent, redaction: $redaction);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

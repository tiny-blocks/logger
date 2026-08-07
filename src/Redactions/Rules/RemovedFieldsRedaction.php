<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions\Rules;

use TinyBlocks\Logger\Internal\Redactor\FieldMatcher;
use TinyBlocks\Logger\Internal\Redactor\FieldRemover;
use TinyBlocks\Logger\Redaction;

/**
 * Drops field values from the payload instead of masking them.
 *
 * <p>Preferred over a mask when the field carries no diagnostic value at all, such as a stack trace
 * or a raw payment code. Nothing about the original value reaches the log, not even its presence.</p>
 */
final readonly class RemovedFieldsRedaction implements Redaction
{
    private Redaction $redactor;

    private function __construct(array $fields)
    {
        $this->redactor = new FieldRemover(fields: new FieldMatcher(fields: $fields));
    }

    /**
     * Creates a RemovedFieldsRedaction from the fields to drop.
     *
     * @param string[] $fields The field names removed from the payload, wildcards accepted.
     * @return RemovedFieldsRedaction The created instance.
     */
    public static function from(array $fields): RemovedFieldsRedaction
    {
        return new RemovedFieldsRedaction(fields: $fields);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

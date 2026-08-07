<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions\Rules;

use TinyBlocks\Logger\Internal\Redactor\FieldMatcher;
use TinyBlocks\Logger\Internal\Redactor\RetainedFieldRedactor;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redaction;

/**
 * Masks every value whose field is not on the allow list, at any depth.
 *
 * <p>The inverse of the other strategies, which name what to hide. Naming what to keep removes the
 * leak by omission: a field added later is masked until it is explicitly allowed. Pair it with
 * {@see ScopedRedaction} to apply the allow list to one branch of the payload instead of all of
 * it.</p>
 */
final readonly class AllowedFieldsRedaction implements Redaction
{
    private Redaction $redactor;

    private function __construct(Mask $mask, array $fields)
    {
        $this->redactor = new RetainedFieldRedactor(
            fields: new FieldMatcher(fields: $fields),
            maskingFunction: $mask->applyTo(...)
        );
    }

    /**
     * Creates an AllowedFieldsRedaction from the mask and the fields left untouched.
     *
     * @param Mask $mask The strategy rendering every value outside the allow list.
     * @param string[] $fields The field names left untouched, wildcards accepted.
     * @return AllowedFieldsRedaction The created instance.
     */
    public static function from(Mask $mask, array $fields): AllowedFieldsRedaction
    {
        return new AllowedFieldsRedaction(mask: $mask, fields: $fields);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

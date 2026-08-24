<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\MalformedRedactionPattern;
use TinyBlocks\Logger\Internal\Redactor\FieldMatcher;
use TinyBlocks\Logger\Internal\Redactor\FieldRemover;
use TinyBlocks\Logger\Internal\Redactor\PatternRedactor;
use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Internal\Redactor\ScopedRedactor;
use TinyBlocks\Logger\Internal\Redactor\VisibleFieldRedactor;

/**
 * The redaction for data the library knows nothing about, pointed at what it must protect.
 *
 * <p>The strategies named after a kind of data are this one with the decisions already made: which
 * fields are covered, which mask renders them, and how much of the value survives. Reach for this
 * one whenever none of them fits, and for whatever they cannot express: dropping a field, keeping
 * an allow list, rewriting matched text, or narrowing another redaction to one branch.</p>
 */
final readonly class GenericRedaction implements Redaction
{
    private function __construct(private Redaction $redactor)
    {
    }

    /**
     * Creates a GenericRedaction applying another redaction only under the given parent field.
     *
     * @param string $parent The parent field name whose sub payloads the redaction reaches.
     * @param Redaction $redaction The redaction applied within that branch.
     * @return GenericRedaction The created instance.
     */
    public static function under(string $parent, Redaction $redaction): GenericRedaction
    {
        return new GenericRedaction(
            redactor: new ScopedRedactor(scope: new FieldMatcher(fields: [$parent]), redaction: $redaction)
        );
    }

    /**
     * Creates a GenericRedaction masking every value whose field is not on the allow list.
     *
     * <p>The inverse of naming what to hide. Naming what to keep removes the leak by omission: a
     * field added later is masked until it is explicitly allowed.</p>
     *
     * @param string[] $fields The field names left untouched, wildcards accepted.
     * @param Mask|null $mask The strategy rendering every value outside the allow list, or null for a fixed mask.
     * @return GenericRedaction The created instance.
     */
    public static function keeping(array $fields, ?Mask $mask = null): GenericRedaction
    {
        $rendering = ($mask ?? Mask::fixed());

        return new GenericRedaction(
            redactor: new VisibleFieldRedactor(
                fields: new FieldMatcher(fields: $fields),
                maskingFunction: $rendering->applyTo(...)
            )
        );
    }

    /**
     * Creates a GenericRedaction masking the given fields, at any depth.
     *
     * @param Mask $mask The strategy rendering the hidden portion of each value.
     * @param string[] $fields The field names covered, wildcards accepted.
     * @param Visibility|null $visibility How much of each value survives, or null to hide it whole.
     * @return GenericRedaction The created instance.
     */
    public static function masking(Mask $mask, array $fields, ?Visibility $visibility = null): GenericRedaction
    {
        $visible = ($visibility ?? Visibility::none());

        return new GenericRedaction(
            redactor: new Redactor(
                fields: new FieldMatcher(fields: $fields),
                maskingFunction: static fn(string $value): string => $visible->applyTo(mask: $mask, value: $value)
            )
        );
    }

    /**
     * Creates a GenericRedaction dropping the given fields, at any depth.
     *
     * <p>The key itself is gone from the output, which is what a value with no meaning once masked
     * deserves: a stack trace, a payment code, a raw user agent.</p>
     *
     * @param string[] $fields The field names removed, wildcards accepted.
     * @return GenericRedaction The created instance.
     */
    public static function removing(array $fields): GenericRedaction
    {
        return new GenericRedaction(redactor: new FieldRemover(fields: new FieldMatcher(fields: $fields)));
    }

    /**
     * Creates a GenericRedaction rewriting every match of the pattern, in every value.
     *
     * <p>Field names are not consulted, which is what reaches sensitive data embedded in free text:
     * an exception message quoting a document, a URL carrying a token.</p>
     *
     * @param string $pattern The regular expression matched against every string value.
     * @param string $replacement The replacement, which may reference capture groups.
     * @return GenericRedaction The created instance.
     * @throws MalformedRedactionPattern If the pattern is not a valid regular expression.
     */
    public static function replacing(string $pattern, string $replacement): GenericRedaction
    {
        return new GenericRedaction(redactor: new PatternRedactor(pattern: $pattern, replacement: $replacement));
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;

/**
 * Masks a postal code, keeping the leading characters that name the region.
 *
 * <p>A full postal code narrows to a street, and sometimes to a building. The leading characters
 * name a region and are what a reader needs to tell one market from another, so the default keeps
 * three of them and hides the rest, with the separators preserved.</p>
 *
 * <p>The same value travels under more than one name, so the default covers the postal code and the
 * zip code alike, however they are spelled: <code>post*code</code> and <code>zip*code</code>. A bare
 * <code>zip</code> is left out, since a field by that name is as likely to carry an archive.</p>
 */
final readonly class PostalCodeRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 3;

    private const array DEFAULT_FIELDS = ['post*code', 'zip*code'];

    private Redaction $redactor;

    private function __construct(array $fields, int $visiblePrefixLength)
    {
        $this->redactor = GenericRedaction::masking(
            mask: Mask::preservingSeparators(),
            fields: $fields,
            visibility: Visibility::edges(prefixLength: $visiblePrefixLength)
        );
    }

    /**
     * Creates a PostalCodeRedaction from the fields to mask and the number of visible leading characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int|null $visiblePrefixLength Leading characters left visible, or null for the default.
     * @return PostalCodeRedaction The created instance.
     * @throws NegativeVisibleLength If the visible prefix length is negative.
     */
    public static function from(array $fields, ?int $visiblePrefixLength = null): PostalCodeRedaction
    {
        return new PostalCodeRedaction(
            fields: $fields,
            visiblePrefixLength: ($visiblePrefixLength ?? self::DEFAULT_VISIBLE_PREFIX_LENGTH)
        );
    }

    /**
     * Builds a PostalCodeRedaction covering the names a postal code travels under.
     *
     * @return PostalCodeRedaction The created instance.
     */
    public static function default(): PostalCodeRedaction
    {
        return PostalCodeRedaction::from(fields: self::DEFAULT_FIELDS);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

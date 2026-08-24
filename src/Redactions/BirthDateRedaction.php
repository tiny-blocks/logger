<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;

/**
 * Masks a date of birth, keeping the year and the shape of the date visible.
 *
 * <p>A full date of birth identifies a person almost as well as a document does, while the year
 * alone answers most of what a reader needs: whether the subject is a minor, which cohort a metric
 * belongs to. The default keeps the four leading characters, which is the year of a date written as
 * ISO 8601, and the separators survive so the shape still reads as a date. For a date written the
 * other way around, name the window that keeps what you meant to keep.</p>
 *
 * <p>The same value travels under more than one name, so the default covers <code>birth*date</code>
 * and <code>date_of_birth</code>, however they are spelled.</p>
 */
final readonly class BirthDateRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 4;

    private const array DEFAULT_FIELDS = ['birth*date', 'date_of_birth'];

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
     * Creates a BirthDateRedaction from the fields to mask and the number of visible leading characters.
     *
     * @param string[] $fields The field names whose values are masked, wildcards accepted.
     * @param int|null $visiblePrefixLength Leading characters left visible, or null for the default.
     * @return BirthDateRedaction The created instance.
     * @throws NegativeVisibleLength If the visible prefix length is negative.
     */
    public static function from(array $fields, ?int $visiblePrefixLength = null): BirthDateRedaction
    {
        return new BirthDateRedaction(
            fields: $fields,
            visiblePrefixLength: ($visiblePrefixLength ?? self::DEFAULT_VISIBLE_PREFIX_LENGTH)
        );
    }

    /**
     * Builds a BirthDateRedaction covering the names a date of birth travels under.
     *
     * @return BirthDateRedaction The created instance.
     */
    public static function default(): BirthDateRedaction
    {
        return BirthDateRedaction::from(fields: self::DEFAULT_FIELDS);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

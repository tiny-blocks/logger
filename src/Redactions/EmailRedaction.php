<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

/**
 * Masks the local part of email field values, keeping a configurable visible prefix and the domain.
 */
final readonly class EmailRedaction implements Redaction
{
    private const int DEFAULT_VISIBLE_PREFIX_LENGTH = 2;

    private Redactor $redactor;

    private function __construct(array $fields, int $visiblePrefixLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static function (string $value) use ($visiblePrefixLength): string {
                $atPosition = mb_strpos($value, '@', 0, 'UTF-8');

                if ($atPosition === false) {
                    return str_repeat('*', mb_strlen($value, 'UTF-8'));
                }

                $domain = mb_substr($value, $atPosition, null, 'UTF-8');
                $localPart = mb_substr($value, 0, $atPosition, 'UTF-8');
                $maskedSuffix = str_repeat('*', max(0, mb_strlen($localPart, 'UTF-8') - $visiblePrefixLength));
                $visiblePrefix = mb_substr($localPart, 0, $visiblePrefixLength, 'UTF-8');
                $template = '%s%s%s';

                return sprintf($template, $visiblePrefix, $maskedSuffix, $domain);
            }
        );
    }

    /**
     * Creates an EmailRedaction from the fields to mask and the number of visible leading characters.
     *
     * @param string[] $fields The field names whose values are masked.
     * @param int $visiblePrefixLength The number of leading characters of the local part left visible.
     * @return EmailRedaction The created instance.
     */
    public static function from(array $fields, int $visiblePrefixLength): EmailRedaction
    {
        return new EmailRedaction(fields: $fields, visiblePrefixLength: $visiblePrefixLength);
    }

    /**
     * Builds an EmailRedaction with the default email field and visible prefix length.
     *
     * @return EmailRedaction The created instance.
     */
    public static function default(): EmailRedaction
    {
        return EmailRedaction::from(fields: ['email'], visiblePrefixLength: self::DEFAULT_VISIBLE_PREFIX_LENGTH);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

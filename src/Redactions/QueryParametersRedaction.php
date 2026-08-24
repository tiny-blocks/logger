<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

/**
 * Masks the query parameters of a logged request, keeping only the ones named.
 *
 * <p>A query parameter is a value someone put in a URL, so it carries whatever the caller sent: an
 * identifier, a document, a token. Naming what stays readable removes the leak by omission, since a
 * parameter added later is masked until it is allowed.</p>
 *
 * <p>It reaches the <code>query_parameters</code> branch, which is where the request log middleware
 * of this ecosystem puts them. For a payload that carries them under another name, compose
 * {@see GenericRedaction::under()} with {@see GenericRedaction::keeping()} instead.</p>
 */
final readonly class QueryParametersRedaction implements Redaction
{
    private const string PARENT = 'query_parameters';

    private Redaction $redactor;

    private function __construct(array $fields)
    {
        $this->redactor = GenericRedaction::under(
            parent: self::PARENT,
            redaction: GenericRedaction::keeping(fields: $fields)
        );
    }

    /**
     * Creates a QueryParametersRedaction keeping only the named parameters readable.
     *
     * @param string[] $fields The parameter names left untouched, wildcards accepted.
     * @return QueryParametersRedaction The created instance.
     */
    public static function keeping(array $fields): QueryParametersRedaction
    {
        return new QueryParametersRedaction(fields: $fields);
    }

    /**
     * Builds a QueryParametersRedaction masking every query parameter.
     *
     * @return QueryParametersRedaction The created instance.
     */
    public static function default(): QueryParametersRedaction
    {
        return QueryParametersRedaction::keeping(fields: []);
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

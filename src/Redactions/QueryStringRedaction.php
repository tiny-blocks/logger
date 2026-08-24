<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

/**
 * Drops the query string of every URL carried in a value, keeping the path intact.
 *
 * <p>A URL reaches the log as an ordinary string, so no field name covers what rides after the
 * question mark: an identifier, a token, a filter carrying a document. The path is what a reader
 * needs to know which route was called, and it survives.</p>
 */
final readonly class QueryStringRedaction implements Redaction
{
    private const string PATTERN = '/(\?)[^\s]*/';

    private const string REPLACEMENT = '${1}';

    private Redaction $redactor;

    private function __construct()
    {
        $this->redactor = GenericRedaction::replacing(pattern: self::PATTERN, replacement: self::REPLACEMENT);
    }

    /**
     * Builds a QueryStringRedaction dropping everything after the question mark of every URL.
     *
     * @return QueryStringRedaction The created instance.
     */
    public static function default(): QueryStringRedaction
    {
        return new QueryStringRedaction();
    }

    public function redact(array $payload): array
    {
        return $this->redactor->redact(payload: $payload);
    }
}

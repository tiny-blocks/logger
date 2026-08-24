<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

final readonly class FieldMatcher
{
    private const string WILDCARD_CHARACTERS = '*?[';

    private array $patterns;

    private array $exactFields;

    public function __construct(array $fields)
    {
        $patterns = [];
        $exactFields = [];

        foreach ($fields as $field) {
            $normalized = FieldMatcher::normalized(value: (string)$field);

            if (strpbrk($normalized, self::WILDCARD_CHARACTERS) === false) {
                $exactFields[$normalized] = $normalized;
                continue;
            }

            $patterns[] = $normalized;
        }

        $this->patterns = $patterns;
        $this->exactFields = $exactFields;
    }

    private static function normalized(string $value): string
    {
        $separated = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $value);
        $split = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', (string)$separated);

        return strtolower((string)$split);
    }

    public function matches(int|string $key): bool
    {
        $candidate = FieldMatcher::normalized(value: (string)$key);

        return isset($this->exactFields[$candidate])
            || ($this->patterns !== []
                && array_any($this->patterns, static fn(string $pattern): bool => fnmatch($pattern, $candidate)));
    }
}

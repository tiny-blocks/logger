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
            if (strpbrk($field, self::WILDCARD_CHARACTERS) === false) {
                $exactFields[$field] = $field;
                continue;
            }

            $patterns[] = $field;
        }

        $this->patterns = $patterns;
        $this->exactFields = $exactFields;
    }

    public function matches(int|string $key): bool
    {
        $candidate = (string)$key;

        return isset($this->exactFields[$candidate])
            || ($this->patterns !== []
                && array_any($this->patterns, static fn(string $pattern): bool => fnmatch($pattern, $candidate)));
    }
}

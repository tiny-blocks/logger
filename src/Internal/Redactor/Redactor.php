<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use Closure;
use TinyBlocks\Logger\Redaction;

final readonly class Redactor implements Redaction
{
    public function __construct(private FieldMatcher $fields, private Closure $maskingFunction)
    {
    }

    public function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->redactValue(key: $key, value: $value);
        }

        return $payload;
    }

    private function maskedValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_is_list($value)
                ? array_map($this->maskedValue(...), $value)
                : $this->redact(payload: $value);
        }

        return is_scalar($value) ? ($this->maskingFunction)((string)$value) : $value;
    }

    private function redactValue(int|string $key, mixed $value): mixed
    {
        if ($this->fields->matches(key: $key)) {
            return $this->maskedValue(value: $value);
        }

        return is_array($value) ? $this->redact(payload: $value) : $value;
    }
}

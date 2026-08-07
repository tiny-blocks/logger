<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use Closure;
use TinyBlocks\Logger\Redaction;

final readonly class RetainedFieldRedactor implements Redaction
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

    private function redactValue(int|string $key, mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->redact(payload: $value);
        }

        if ($this->fields->matches(key: $key) || !is_scalar($value)) {
            return $value;
        }

        return ($this->maskingFunction)((string)$value);
    }
}

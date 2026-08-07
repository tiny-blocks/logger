<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Logger\Redaction;

final readonly class ScopedRedactor implements Redaction
{
    public function __construct(private FieldMatcher $scope, private Redaction $redaction)
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
        if (!is_array($value)) {
            return $value;
        }

        $descended = $this->redact(payload: $value);

        return $this->scope->matches(key: $key) ? $this->redaction->redact(payload: $descended) : $descended;
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use Closure;
use TinyBlocks\Logger\Redaction;

final readonly class FieldRedactor implements Redaction
{
    public function __construct(private string $field, private Closure $maskingFunction)
    {
    }

    public function redact(array $data): array
    {
        return $this->apply(data: $data);
    }

    private function apply(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->apply(data: $value);
                continue;
            }

            if ($key === $this->field && is_string($value)) {
                $data[$key] = ($this->maskingFunction)($value);
            }
        }

        return $data;
    }
}

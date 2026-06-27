<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use Closure;
use TinyBlocks\Logger\Redaction;

final readonly class Redactor implements Redaction
{
    public function __construct(private array $fields, private Closure $maskingFunction)
    {
    }

    public function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->redact(payload: $value);
                continue;
            }

            if (in_array($key, $this->fields, true) && is_string($value)) {
                $payload[$key] = ($this->maskingFunction)($value);
            }
        }

        return $payload;
    }
}

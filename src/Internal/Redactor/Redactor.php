<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use Closure;
use TinyBlocks\Logger\Redaction;

final readonly class Redactor implements Redaction
{
    /** @param string[] $fields */
    public function __construct(private array $fields, private Closure $maskingFunction)
    {
    }

    public function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact(data: $value);
                continue;
            }

            if (in_array($key, $this->fields, true) && is_string($value)) {
                $data[$key] = ($this->maskingFunction)($value);
            }
        }

        return $data;
    }
}

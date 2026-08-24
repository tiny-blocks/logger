<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Logger\Redactions\Redaction;

final readonly class FieldRemover implements Redaction
{
    public function __construct(private FieldMatcher $fields)
    {
    }

    public function redact(array $payload): array
    {
        $retained = [];

        foreach ($payload as $key => $value) {
            if ($this->fields->matches(key: $key)) {
                continue;
            }

            $retained[$key] = is_array($value) ? $this->redact(payload: $value) : $value;
        }

        return $retained;
    }
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Logger\Redaction;

final class Redactions extends Collection
{
    public function applyTo(array $data): array
    {
        /** @var Redaction $redaction */
        foreach ($this as $redaction) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $redaction->redact(data: $value);
                    continue;
                }

                $data[$key] = $redaction->redact(data: [$key => $value])[$key];
            }
        }

        return $data;
    }
}

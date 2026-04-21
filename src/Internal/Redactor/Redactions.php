<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Logger\Redaction;

final class Redactions extends Collection
{
    public function applyTo(array $data): array
    {
        return $this->reduce(
            accumulator: static fn(array $carry, Redaction $redaction): array => $redaction->redact(data: $carry),
            initial: $data
        );
    }
}

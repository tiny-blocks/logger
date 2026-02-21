<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Logger\Redaction;

final readonly class Redactions
{
    private array $elements;

    private function __construct(Redaction ...$elements)
    {
        $this->elements = $elements;
    }

    public static function from(Redaction ...$redactions): Redactions
    {
        return new Redactions(...$redactions);
    }

    public static function createEmpty(): Redactions
    {
        return new Redactions();
    }

    public function applyTo(array $data): array
    {
        foreach ($this->elements as $redaction) {
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

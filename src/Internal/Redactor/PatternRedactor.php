<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal\Redactor;

use TinyBlocks\Logger\Exceptions\MalformedRedactionPattern;
use TinyBlocks\Logger\Redactions\Redaction;

final readonly class PatternRedactor implements Redaction
{
    public function __construct(private string $pattern, private string $replacement)
    {
        if (@preg_match($pattern, '') === false) {
            $template = 'Pattern is not a valid regular expression: %s.';

            throw new MalformedRedactionPattern(message: sprintf($template, $pattern));
        }
    }

    public function redact(array $payload): array
    {
        return array_map($this->redactValue(...), $payload);
    }

    private function redactValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->redact(payload: $value);
        }

        return is_string($value) ? preg_replace($this->pattern, $this->replacement, $value) : $value;
    }
}

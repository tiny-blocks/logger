<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

final readonly class PasswordRedaction implements Redaction
{
    private Redactor $redactor;

    private function __construct(array $fields)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static function (string $value): string {
                return str_repeat('*', strlen($value));
            }
        );
    }

    public static function from(array $fields): PasswordRedaction
    {
        return new PasswordRedaction(fields: $fields);
    }

    public static function default(): PasswordRedaction
    {
        return self::from(fields: ['password']);
    }

    public function redact(array $data): array
    {
        return $this->redactor->redact(data: $data);
    }
}

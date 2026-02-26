<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\Redactor;
use TinyBlocks\Logger\Redaction;

final readonly class PasswordRedaction implements Redaction
{
    private const int DEFAULT_FIXED_MASK_LENGTH = 8;

    private Redactor $redactor;

    private function __construct(array $fields, int $fixedMaskLength)
    {
        $this->redactor = new Redactor(
            fields: $fields,
            maskingFunction: static fn(): string => str_repeat('*', $fixedMaskLength)
        );
    }

    public static function from(
        array $fields,
        int $fixedMaskLength = self::DEFAULT_FIXED_MASK_LENGTH
    ): PasswordRedaction {
        return new PasswordRedaction(fields: $fields, fixedMaskLength: $fixedMaskLength);
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

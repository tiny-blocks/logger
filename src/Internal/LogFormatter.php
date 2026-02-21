<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use TinyBlocks\Logger\LogLevel;

final readonly class LogFormatter
{
    private const string TEMPLATE = "%s component=%s correlationId=%s level=%s key=%s data=%s\n";

    public function __construct(private string $component)
    {
    }

    public function format(LogLevel $level, string $key, array $data, ?LogContext $context = null): string
    {
        $timestamp = new DateTimeImmutable()->format(DateTimeInterface::ATOM);
        $correlationId = $context?->correlationId ?? '';
        $encodedData = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return sprintf(
            self::TEMPLATE,
            $timestamp,
            $this->component,
            $correlationId,
            $level->value,
            $key,
            $encodedData
        );
    }
}

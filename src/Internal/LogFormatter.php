<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use TinyBlocks\Logger\LogContext;

final readonly class LogFormatter
{
    private const string DEFAULT_TEMPLATE = "%s component=%s correlation_id=%s level=%s key=%s data=%s\n";
    private const string EMPTY_CORRELATION_ID = '';
    private const string ENCODING_FAILURE_PAYLOAD = '{"error":"encoding_failed"}';

    private function __construct(private string $template, private string $component)
    {
    }

    private static function sanitize(string $value): string
    {
        return strtr($value, ["\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
    }

    public static function fromTemplate(string $template, string $component): LogFormatter
    {
        return new LogFormatter(template: $template, component: $component);
    }

    public static function fromComponent(string $component): LogFormatter
    {
        return new LogFormatter(template: self::DEFAULT_TEMPLATE, component: $component);
    }

    public function format(string $key, LogLevel $level, array $payload, ?LogContext $context = null): string
    {
        $timestamp = new DateTimeImmutable()->format(DateTimeInterface::ATOM);
        $correlationId = is_null($context) ? self::EMPTY_CORRELATION_ID : $context->correlationId;

        try {
            $encodedData = json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            $encodedData = self::ENCODING_FAILURE_PAYLOAD;
        }

        return sprintf(
            $this->template,
            $timestamp,
            LogFormatter::sanitize(value: $this->component),
            LogFormatter::sanitize(value: $correlationId),
            $level->value,
            LogFormatter::sanitize(value: $key),
            $encodedData
        );
    }
}

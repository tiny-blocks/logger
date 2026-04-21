<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\LogLevel;

final readonly class LogFormatter
{
    private const string DEFAULT_TEMPLATE = "%s component=%s correlation_id=%s level=%s key=%s data=%s\n";
    private const string EMPTY_CORRELATION_ID = '';
    private const string ENCODING_FAILURE_PAYLOAD = '{"error":"encoding_failed"}';

    private function __construct(private string $component, private string $template)
    {
    }

    public static function fromComponent(string $component): LogFormatter
    {
        return new LogFormatter(component: $component, template: self::DEFAULT_TEMPLATE);
    }

    public static function fromTemplate(string $component, string $template): LogFormatter
    {
        return new LogFormatter(component: $component, template: $template);
    }

    public function format(string $key, array $data, LogLevel $level, ?LogContext $context = null): string
    {
        $timestamp = new DateTimeImmutable()->format(DateTimeInterface::ATOM);
        $correlationId = is_null($context) ? self::EMPTY_CORRELATION_ID : $context->correlationId;

        try {
            $encodedData = json_encode(
                $data,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            $encodedData = self::ENCODING_FAILURE_PAYLOAD;
        }

        return sprintf(
            $this->template,
            $timestamp,
            self::sanitize(value: $this->component),
            self::sanitize(value: $correlationId),
            $level->value,
            self::sanitize(value: $key),
            $encodedData
        );
    }

    private static function sanitize(string $value): string
    {
        return strtr($value, ["\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
    }
}

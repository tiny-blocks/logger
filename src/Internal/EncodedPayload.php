<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal;

use JsonException;

final readonly class EncodedPayload
{
    private const string ENCODING_FAILURE = '{"error":"encoding_failed"}';

    private function __construct(private array $payload)
    {
    }

    public static function from(array $payload): EncodedPayload
    {
        return new EncodedPayload(payload: $payload);
    }

    public function toString(): string
    {
        try {
            return json_encode(
                $this->payload,
                (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        } catch (JsonException) {
            return self::ENCODING_FAILURE;
        }
    }
}

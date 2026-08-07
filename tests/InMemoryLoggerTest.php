<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\Exceptions\UnknownLogLevel;
use TinyBlocks\Logger\InMemoryLogger;
use TinyBlocks\Logger\LogContext;

final class InMemoryLoggerTest extends TestCase
{
    public function testEntriesWhenNothingLoggedThenHoldsNoEntry(): void
    {
        /** @Given an in-memory logger */
        $logger = InMemoryLogger::create();

        /** @When reading the recorded entries before logging anything */
        $entries = $logger->entries();

        /** @Then no entry is recorded */
        self::assertTrue($entries->isEmpty());
    }

    public function testLogWhenContextDerivedThenBothInstancesSeeIt(): void
    {
        /** @Given an in-memory logger */
        $logger = InMemoryLogger::create();

        /** @And a contextual logger derived from it */
        $contextual = $logger->withContext(context: LogContext::from(correlationId: 'req-abc-123'));

        /** @When logging through the contextual logger */
        $contextual->error(message: 'payment.failed');

        /** @Then the entry carries the correlation context and is visible from the original logger */
        self::assertSame(
            [
                [
                    'key'     => 'payment.failed',
                    'level'   => 'ERROR',
                    'context' => 'req-abc-123',
                    'payload' => []
                ]
            ],
            $logger->entries()->toArray()
        );
    }

    public function testLogWhenEntryRecordedThenKeepsLevelAndPayload(): void
    {
        /** @Given an in-memory logger */
        $logger = InMemoryLogger::create();

        /** @When logging an entry with a payload */
        $logger->info(message: 'user.created', context: ['document' => '12345678900']);

        /** @Then the entry is recorded with its level and its untouched payload */
        self::assertSame(
            [
                [
                    'key'     => 'user.created',
                    'level'   => 'INFO',
                    'context' => null,
                    'payload' => ['document' => '12345678900']
                ]
            ],
            $logger->entries()->toArray()
        );
    }

    public function testLogWhenLevelIsUnknownThenThrowsUnknownLogLevel(): void
    {
        /** @Given an in-memory logger */
        $logger = InMemoryLogger::create();

        /** @Then logging at an unsupported level raises an unknown log level error */
        $this->expectException(UnknownLogLevel::class);

        /** @When logging at a level outside the supported set */
        $logger->log('not-a-level', 'some.key');
    }
}

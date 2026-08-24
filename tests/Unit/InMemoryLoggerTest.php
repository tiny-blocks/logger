<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\Correlation;
use TinyBlocks\Logger\Exceptions\UnknownLogLevel;
use TinyBlocks\Logger\InMemoryLogger;

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
                    'key'         => 'user.created',
                    'level'       => 'INFO',
                    'payload'     => ['document' => '12345678900'],
                    'correlation' => null
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

    public function testLogWhenCorrelationDerivedThenBothInstancesSeeIt(): void
    {
        /** @Given an in-memory logger */
        $logger = InMemoryLogger::create();

        /** @And a correlated logger derived from it */
        $correlated = $logger->withCorrelation(correlation: Correlation::from(correlationId: 'req-abc-123'));

        /** @When logging through the correlated logger */
        $correlated->error(message: 'payment.failed');

        /** @Then the entry carries the correlation and is visible from the original logger */
        self::assertSame(
            [
                [
                    'key'         => 'payment.failed',
                    'level'       => 'ERROR',
                    'payload'     => [],
                    'correlation' => 'req-abc-123'
                ]
            ],
            $logger->entries()->toArray()
        );
    }
}

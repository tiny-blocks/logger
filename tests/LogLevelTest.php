<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\LogLevel;
use TinyBlocks\Logger\StructuredLogger;

final class LogLevelTest extends TestCase
{
    private InMemoryStream $logStream;

    protected function setUp(): void
    {
        $this->logStream = InMemoryStream::create();
    }

    protected function tearDown(): void
    {
        $this->logStream->close();
    }

    public function testLogWhenAtMinimumLevelThenWritesTheEntry(): void
    {
        /** @Given a structured logger that discards anything below warning */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'order-service')
            ->withMinimumLevel(minimumLevel: LogLevel::WARNING)
            ->build();

        /** @When logging exactly at the minimum level */
        $logger->warning(message: 'stock.low', context: ['remaining' => 2]);

        /** @Then the entry reaches the stream */
        self::assertStringContainsString('level=WARNING', $this->logStream->contents());
    }

    #[DataProvider('levelsWithSeverity')]
    public function testSeverityWhenLevelGivenThenReturnsItsRank(LogLevel $level, int $severity): void
    {
        /** @Given a log level and the rank it holds in the PSR-3 scale */

        /** @When reading the severity of the level */
        $rank = $level->severity();

        /** @Then the rank matches its position in the scale */
        self::assertSame($severity, $rank);
    }

    public function testLogWhenBelowMinimumLevelThenWritesNothing(): void
    {
        /** @Given a structured logger that discards anything below warning */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'order-service')
            ->withMinimumLevel(minimumLevel: LogLevel::WARNING)
            ->build();

        /** @When logging below the minimum level */
        $logger->info(message: 'order.placed', context: ['orderId' => 42]);

        /** @Then nothing reaches the stream */
        self::assertSame('', $this->logStream->contents());
    }

    public function testIsAtLeastWhenSameLevelThenReachesThreshold(): void
    {
        /** @Given a threshold at the error level */
        $threshold = LogLevel::ERROR;

        /** @When comparing the same level against it */
        $reaches = LogLevel::ERROR->isAtLeast(threshold: $threshold);

        /** @Then the level reaches the threshold */
        self::assertTrue($reaches);
    }

    public function testLogWhenLevelIsLowercaseThenResolvesTheLevel(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'order-service')
            ->build();

        /** @When logging through the PSR-3 entry point with a lowercase level */
        $logger->log('notice', 'order.reviewed');

        /** @Then the level is resolved and written in upper case */
        self::assertStringContainsString('level=NOTICE', $this->logStream->contents());
    }

    public function testIsAtLeastWhenLouderLevelThenReachesThreshold(): void
    {
        /** @Given a threshold at the warning level */
        $threshold = LogLevel::WARNING;

        /** @When comparing a more severe level against it */
        $reaches = LogLevel::CRITICAL->isAtLeast(threshold: $threshold);

        /** @Then the level reaches the threshold */
        self::assertTrue($reaches);
    }

    public function testIsAtLeastWhenQuieterLevelThenMissesThreshold(): void
    {
        /** @Given a threshold at the warning level */
        $threshold = LogLevel::WARNING;

        /** @When comparing a less severe level against it */
        $reaches = LogLevel::NOTICE->isAtLeast(threshold: $threshold);

        /** @Then the level misses the threshold */
        self::assertFalse($reaches);
    }

    public static function levelsWithSeverity(): array
    {
        return [
            'debug'     => [LogLevel::DEBUG, 0],
            'info'      => [LogLevel::INFO, 1],
            'notice'    => [LogLevel::NOTICE, 2],
            'warning'   => [LogLevel::WARNING, 3],
            'error'     => [LogLevel::ERROR, 4],
            'critical'  => [LogLevel::CRITICAL, 5],
            'alert'     => [LogLevel::ALERT, 6],
            'emergency' => [LogLevel::EMERGENCY, 7]
        ];
    }
}

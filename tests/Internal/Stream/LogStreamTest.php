<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger\Internal\Stream;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\Internal\Stream\LogStream;

final class LogStreamTest extends TestCase
{
    /** @noinspection PhpConditionAlreadyCheckedInspection */
    public function testFromWithoutResourceFallsBackToStderr(): void
    {
        /** @Given no resource is provided */
        /** @When creating a LogStream without a resource */
        $logStream = LogStream::from();

        /** @Then a LogStream instance should be returned (using stderr as fallback) */
        self::assertInstanceOf(LogStream::class, $logStream);
    }
}

<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger\Internal\Stream;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\Internal\Stream\LogStream;

final class LogStreamTest extends TestCase
{
    public function testFromWithCustomResource(): void
    {
        /** @Given a custom writable stream */
        $stream = fopen('php://memory', 'r+');

        /** @When creating a LogStream with the custom resource */
        $logStream = LogStream::from(resource: $stream);

        /** @Then it should write content to the provided stream */
        $logStream->write('custom resource test');

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame('custom resource test', $output);

        fclose($stream);
    }

    public function testFromWithNullFallsBackToStderr(): void
    {
        /** @Given no resource is provided */
        /** @When creating a LogStream without arguments */
        $logStream = LogStream::from();

        /** @Then it should be created successfully and be writable without errors */
        $logStream->write('fallback test');

        self::assertTrue(true);
    }
}

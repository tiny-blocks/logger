<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use Psr\Log\LoggerTrait;
use Stringable;
use TinyBlocks\Logger\Exceptions\UnknownLogLevel;
use TinyBlocks\Logger\Internal\LogEntryRecorder;

/**
 * Logger that keeps every entry in memory instead of writing it to a stream.
 *
 * <p>Written for the tests of the systems that consume this library, where the assertion is about
 * what was logged rather than how it was rendered. Payloads are recorded exactly as received, with
 * no redaction and no formatting, so assertions read the original values.</p>
 *
 * <p>Loggers derived through {@see withCorrelation} record into the same store as the instance they
 * come from, so entries logged through a derived instance are visible from either one.</p>
 */
final readonly class InMemoryLogger implements Logger
{
    use LoggerTrait;

    private function __construct(private LogEntryRecorder $recorder, private ?Correlation $correlation)
    {
    }

    /**
     * Creates an InMemoryLogger with no bound correlation and no recorded entries.
     *
     * @return InMemoryLogger The created instance.
     */
    public static function create(): InMemoryLogger
    {
        return new InMemoryLogger(recorder: new LogEntryRecorder(), correlation: null);
    }

    /**
     * Records a log entry in memory.
     *
     * @param mixed $level The severity, as received by the PSR-3 contract.
     * @param string|Stringable $message The message key identifying the event.
     * @param array<string, mixed> $context The context data carried by the entry.
     * @throws UnknownLogLevel If the level is outside the supported PSR-3 set.
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->recorder->record(
            entry: LogEntry::of(
                key: (string)$message,
                level: LogLevel::fromPsrLevel(level: $level),
                payload: $context,
                correlation: $this->correlation
            )
        );
    }

    /**
     * Returns the entries recorded so far.
     *
     * @return LogEntries The recorded entries, in the order they were logged.
     */
    public function entries(): LogEntries
    {
        return $this->recorder->toLogEntries();
    }

    public function withCorrelation(Correlation $correlation): InMemoryLogger
    {
        return new InMemoryLogger(recorder: $this->recorder, correlation: $correlation);
    }
}

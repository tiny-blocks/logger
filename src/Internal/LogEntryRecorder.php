<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal;

use TinyBlocks\Logger\LogEntries;
use TinyBlocks\Logger\LogEntry;

final class LogEntryRecorder
{
    private array $entries = [];

    public function record(LogEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function toLogEntries(): LogEntries
    {
        return LogEntries::createFrom(elements: $this->entries);
    }
}

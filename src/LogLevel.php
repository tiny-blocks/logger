<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

/**
 * Represents the severity level of a log entry.
 */
enum LogLevel: string
{
    case INFO = 'INFO';
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
}

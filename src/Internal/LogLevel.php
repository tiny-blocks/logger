<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Internal;

enum LogLevel: string
{
    case INFO = 'INFO';
    case ALERT = 'ALERT';
    case DEBUG = 'DEBUG';
    case ERROR = 'ERROR';
    case NOTICE = 'NOTICE';
    case WARNING = 'WARNING';
    case CRITICAL = 'CRITICAL';
    case EMERGENCY = 'EMERGENCY';
}

<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

enum LogLevel: string
{
    case INFO = 'INFO';
    case ERROR = 'ERROR';
    case DEBUG = 'DEBUG';
    case ALERT = 'ALERT';
    case NOTICE = 'NOTICE';
    case WARNING = 'WARNING';
    case CRITICAL = 'CRITICAL';
    case EMERGENCY = 'EMERGENCY';
}

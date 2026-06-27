<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Exceptions;

use Psr\Log\InvalidArgumentException;

/**
 * Raised when a log level outside the supported PSR-3 set is provided.
 */
final class UnknownLogLevel extends InvalidArgumentException
{
}

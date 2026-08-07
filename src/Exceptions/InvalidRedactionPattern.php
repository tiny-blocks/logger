<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a redaction is configured with a pattern the regular expression engine rejects.
 */
final class InvalidRedactionPattern extends InvalidArgumentException
{
}

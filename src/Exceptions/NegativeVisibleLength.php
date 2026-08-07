<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a redaction is configured to leave a negative number of characters visible.
 */
final class NegativeVisibleLength extends InvalidArgumentException
{
}

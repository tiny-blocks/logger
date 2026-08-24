<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Exceptions;

use InvalidArgumentException;

final class RedactedDimension extends InvalidArgumentException
{
    public function __construct(private readonly string $dimension)
    {
        parent::__construct(
            message: sprintf(
                'The dimension <%s> is covered by a redaction. A dimension value reaches the metric index of '
                . 'the backend, where no redaction and no log retention can reach it. Emit it as a field instead.',
                $this->dimension
            )
        );
    }
}

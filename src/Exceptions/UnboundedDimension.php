<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Exceptions;

use InvalidArgumentException;

final class UnboundedDimension extends InvalidArgumentException
{
    public function __construct(private readonly string $dimension)
    {
        parent::__construct(
            message: sprintf(
                'The dimension <%s> was declared unbounded. Emit it as a field of the same record instead.',
                $this->dimension
            )
        );
    }
}

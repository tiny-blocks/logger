<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Exceptions;

use InvalidArgumentException;

final class BlankMetricIdentifier extends InvalidArgumentException
{
    public function __construct(private readonly string $identifier)
    {
        parent::__construct(
            message: sprintf('The metric %s cannot be blank.', $this->identifier)
        );
    }
}

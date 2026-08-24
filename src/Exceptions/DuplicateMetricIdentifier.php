<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Exceptions;

use InvalidArgumentException;

final class DuplicateMetricIdentifier extends InvalidArgumentException
{
    public function __construct(private readonly string $identifier)
    {
        parent::__construct(
            message: sprintf(
                'The name <%s> is carried by a field and by a dimension at once. Both land at the root of the '
                . 'same record, so one would silently replace the other.',
                $this->identifier
            )
        );
    }
}

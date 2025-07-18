<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

class UnexpectedResultTypeException extends RuntimeException
{
    public function __construct(string $expectedType, string $actualType)
    {
        parent::__construct(\sprintf(
            'Unexpected response type: expected "%s", got "%s".',
            $expectedType,
            $actualType
        ));
    }
}

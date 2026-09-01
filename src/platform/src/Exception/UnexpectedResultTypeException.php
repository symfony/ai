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
    /**
     * @param string|null $hint what the caller can do about it, when the mismatch has a known remedy
     */
    public function __construct(string $expectedType, string $actualType, ?string $hint = null)
    {
        parent::__construct(\sprintf(
            'Unexpected response type: expected "%s", got "%s".%s',
            $expectedType,
            $actualType,
            null !== $hint ? ' '.$hint : '',
        ));
    }
}

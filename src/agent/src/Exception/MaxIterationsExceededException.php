<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Exception;

/**
 * Exception thrown when the maximum number of tool calling iterations is exceeded.
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class MaxIterationsExceededException extends RuntimeException
{
    public function __construct(int $maxToolCalls, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('Maximum number of tool calling iterations (%d) exceeded.', $maxToolCalls),
            0,
            $previous
        );
    }
}

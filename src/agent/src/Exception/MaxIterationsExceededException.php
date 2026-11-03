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

use Symfony\AI\Platform\Exception\ErrorCodeAwareInterface;

/**
 * Exception thrown when the maximum number of tool calling iterations is exceeded.
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class MaxIterationsExceededException extends RuntimeException implements ErrorCodeAwareInterface
{
    public function __construct(int $maxToolCalls, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('Maximum number of tool calling iterations (%d) exceeded.', $maxToolCalls),
            0,
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'max_iterations_exceeded';
    }

    public function isRetryable(): bool
    {
        return false;
    }
}

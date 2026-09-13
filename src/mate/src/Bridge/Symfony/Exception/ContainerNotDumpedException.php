<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Exception;

use Symfony\AI\Mate\Exception\RuntimeException;

/**
 * Thrown when no compiled container has been dumped yet.
 *
 * Answering with an empty result would be indistinguishable from an application that really
 * has no matching service.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ContainerNotDumpedException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

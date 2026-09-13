<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Exception;

/**
 * Thrown when the container cannot be compiled, which a mistake in a user-supplied service file
 * is the usual cause of.
 *
 * Letting the compiler's own exception escape takes down every command, including the ones that
 * would be used to repair the file, and reports it as a stack trace through the DI internals.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ContainerCompilationException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

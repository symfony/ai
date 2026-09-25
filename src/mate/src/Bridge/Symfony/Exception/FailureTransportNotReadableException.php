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
 * Thrown when a failure transport cannot be read passively: its DSN or connection cannot be
 * resolved, its storage cannot be opened, or the transport kind cannot be read without
 * consuming messages.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class FailureTransportNotReadableException extends RuntimeException
{
}

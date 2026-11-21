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
 * Thrown when a stored message is not an envelope in a format the failed-messages tool can read.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class UndecodableMessageException extends RuntimeException
{
}

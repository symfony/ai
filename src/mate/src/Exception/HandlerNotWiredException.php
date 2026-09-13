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
 * Thrown when a discovered handler class is not registered as a service.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class HandlerNotWiredException extends RuntimeException
{
}

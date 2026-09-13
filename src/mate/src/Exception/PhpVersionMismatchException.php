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
 * Thrown when Mate runs under a different PHP version than the project recorded.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PhpVersionMismatchException extends RuntimeException
{
}

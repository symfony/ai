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
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class UnsupportedVersionException extends RuntimeException
{
    public static function forConsole(): self
    {
        return new self('Unsupported version of symfony/console. We cannot add commands.');
    }
}

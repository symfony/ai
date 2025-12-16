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

use Symfony\AI\Mate\Exception\InvalidArgumentException;

/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class XmlContainerCouldNotBeLoadedException extends InvalidArgumentException
{
    public static function forContainerDoesNotExist(string $path): self
    {
        return new self(\sprintf('Container "%s" does not exist', $path));
    }

    public static function forContainerCannotBeParsed(string $path): self
    {
        return new self(\sprintf('Container "%s" cannot be parsed', $path));
    }
}

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

/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class XmlContainerPathIsNotConfiguredException extends XmlContainerCouldNotBeLoadedException
{
    public static function emptyPath(): self
    {
        return new self('Failed to configure path to Symfony container. You passed an empty string');
    }
}

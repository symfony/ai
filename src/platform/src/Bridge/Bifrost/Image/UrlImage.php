<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Image;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class UrlImage
{
    public function __construct(
        public readonly string $url,
    ) {
        if ('' === $url) {
            throw new InvalidArgumentException('The image URL must be given.');
        }
    }
}

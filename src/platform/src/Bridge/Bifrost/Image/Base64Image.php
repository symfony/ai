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
final class Base64Image
{
    public function __construct(
        public readonly string $encodedImage,
    ) {
        if ('' === $encodedImage) {
            throw new InvalidArgumentException('The base64-encoded image must be given.');
        }
    }
}

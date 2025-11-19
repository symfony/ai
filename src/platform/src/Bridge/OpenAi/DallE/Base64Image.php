<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\DallE;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class Base64Image
{
    public function __construct(
        public readonly string $encodedImage,
    ) {
        if ('' === $encodedImage) {
            throw new InvalidArgumentException('The base64 encoded image generated must be given.');
        }
    }
}

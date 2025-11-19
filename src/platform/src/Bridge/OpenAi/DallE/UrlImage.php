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
final class UrlImage
{
    public function __construct(
        public readonly string $url,
    ) {
        if ('' === $url) {
            throw new InvalidArgumentException('The image url must be given.');
        }
    }
}

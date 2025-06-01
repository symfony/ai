<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAI\DallE;

use Webmozart\Assert\Assert;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final readonly class Base64Image
{
    public function __construct(
        public string $encodedImage,
    ) {
        Assert::stringNotEmpty($encodedImage, 'The base64 encoded image generated must be given.');
    }
}

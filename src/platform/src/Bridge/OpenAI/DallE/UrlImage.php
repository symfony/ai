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
final readonly class UrlImage
{
    public function __construct(
        public string $url,
    ) {
        Assert::stringNotEmpty($url, 'The image url must be given.');
    }
}

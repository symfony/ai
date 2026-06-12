<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

use Symfony\AI\Platform\CacheableInputInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ImageUrl implements ContentInterface, CacheableInputInterface
{
    public function __construct(
        private readonly string $url,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCacheKey(): string
    {
        return hash('xxh128', $this->url);
    }
}

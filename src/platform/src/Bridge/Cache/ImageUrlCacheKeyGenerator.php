<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\ImageUrl;

/**
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class ImageUrlCacheKeyGenerator implements CacheKeyGenerator
{
    public function supports(object $input): bool
    {
        return $input instanceof ImageUrl;
    }

    public function generate(object $input): string
    {
        if (!$input instanceof ImageUrl) {
            throw new InvalidArgumentException(\sprintf('"%s" only supports "%s" inputs, "%s" given.', self::class, ImageUrl::class, get_debug_type($input)));
        }

        return hash('xxh128', $input->getUrl());
    }
}

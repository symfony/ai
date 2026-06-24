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

/**
 * Builds a stable, cache-key-safe identifier for a non-scalar platform input.
 *
 * Implementations are registered with {@see CachePlatform} and tried in order;
 * the first one whose {@see self::supports()} returns true keys the input. This
 * keeps the knowledge of how to cache a given input type inside the Cache bridge
 * instead of leaking it into the Platform component or its content classes.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface CacheKeyGenerator
{
    public function supports(object $input): bool;

    /**
     * Returns a stable, cache-key-safe identifier for the given input.
     *
     * The value is used verbatim as part of a cache key, so it must not contain
     * the PSR-6 reserved characters ("{}()/\@:"). Hash any value that might
     * (e.g. a URL or raw bytes).
     */
    public function generate(object $input): string;
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

/**
 * Implemented by inputs that can advertise a stable cache key, so caching
 * decorators (e.g. the Cache platform bridge) can key on them.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
interface CacheableInputInterface
{
    /**
     * Returns a stable, cache-key-safe identifier for this input's content.
     *
     * The returned value is used verbatim as part of a cache key, so it must not
     * contain the PSR-6 reserved characters ("{}()/\@:"). Hash any value that might
     * (e.g. a URL or raw bytes).
     */
    public function getCacheKey(): string;
}

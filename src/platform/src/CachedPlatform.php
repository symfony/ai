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

use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class CachedPlatform implements PlatformInterface
{
    public function __construct(
        private PlatformInterface $platform,
        private CacheInterface $cache,
    ) {
    }

    public function invoke(string $model, object|array|string $input, array $options = []): ResultPromise
    {
        $invokeCall = fn (string $model, object|array|string $input, array $options = []): ResultPromise => $this->platform->invoke($model, $input, $options);

        if ($this->cache instanceof CacheInterface && (\array_key_exists('prompt_cache_key', $options) && '' !== $options['prompt_cache_key'])) {
            $cacheKey = \sprintf('%s_%s', $options['prompt_cache_key'], md5($model));

            unset($options['prompt_cache_key']);

            return $this->cache->get($cacheKey, static fn (): ResultPromise => $invokeCall($model, $input, $options));
        }

        return $invokeCall($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}

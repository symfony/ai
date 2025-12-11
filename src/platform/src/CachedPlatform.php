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
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CachedPlatform implements PlatformInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly (CacheInterface&TagAwareAdapterInterface)|null $cache = null,
        private readonly ?string $cacheKey = null,
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $invokeCall = fn (string $model, array|string|object $input, array $options = []): DeferredResult => $this->platform->invoke($model, $input, $options);

        if ($this->cache instanceof CacheInterface && (\array_key_exists('prompt_cache_key', $options) && '' !== $options['prompt_cache_key'])) {
            $cacheKey = \sprintf('%s_%s_%s', $this->cacheKey ?? $options['prompt_cache_key'], md5($model), \is_string($input) ? md5($input) : md5(json_encode($input)));

            unset($options['prompt_cache_key']);

            return $this->cache->get($cacheKey, static function (ItemInterface $item) use ($invokeCall, $model, $input, $options, $cacheKey): DeferredResult {
                $item->tag($model);

                $result = $invokeCall($model, $input, $options);

                $result = new DeferredResult(
                    $result->getResultConverter(),
                    $result->getRawResult(),
                    $options,
                );

                $result->getMetadata()->set([
                    'cached' => true,
                    'cache_key' => $cacheKey,
                    'cached_at' => (new \DateTimeImmutable())->getTimestamp(),
                ]);

                return $result;
            });
        }

        return $invokeCall($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}

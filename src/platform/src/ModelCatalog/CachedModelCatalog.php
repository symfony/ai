<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelCatalog;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Caches the lookups of a wrapped catalog.
 *
 * Useful for API-based catalogs (e.g. the Ollama bridge) that would otherwise
 * hit a remote endpoint on every resolution: the wrapped catalog is queried
 * once per model name and the result is served from the cache afterwards,
 * persisting across requests when backed by a shared cache pool.
 *
 * Lookups that fail with a {@see \Symfony\AI\Platform\Exception\ModelNotFoundException}
 * are not cached, so a model that appears later resolves on the next call.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CachedModelCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly ModelCatalogInterface $catalog,
        private readonly CacheInterface $cache,
        private readonly ?int $ttl = null,
    ) {
    }

    public function getModel(string $modelName): Model
    {
        return $this->cache->get(
            'symfony_ai_model_catalog.model.'.rawurlencode($modelName),
            function (ItemInterface $item) use ($modelName): Model {
                if (null !== $this->ttl) {
                    $item->expiresAfter($this->ttl);
                }

                return $this->catalog->getModel($modelName);
            },
        );
    }

    /**
     * @return array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    public function getModels(): array
    {
        return $this->cache->get(
            'symfony_ai_model_catalog.models',
            function (ItemInterface $item): array {
                if (null !== $this->ttl) {
                    $item->expiresAfter($this->ttl);
                }

                return $this->catalog->getModels();
            },
        );
    }
}

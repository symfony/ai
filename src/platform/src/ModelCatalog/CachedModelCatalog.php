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
 * Caches the lookups of a wrapped catalog, except for unknown models.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CachedModelCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly ModelCatalogInterface $catalog,
        private readonly CacheInterface $cache,
        private readonly ?int $ttl = null,
        private readonly ?string $namespace = null,
    ) {
    }

    public function getModel(string $modelName): Model
    {
        return $this->cache->get(
            $this->getCacheKey('model.'.rawurlencode($modelName)),
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
            $this->getCacheKey('models'),
            function (ItemInterface $item): array {
                if (null !== $this->ttl) {
                    $item->expiresAfter($this->ttl);
                }

                return $this->catalog->getModels();
            },
        );
    }

    private function getCacheKey(string $suffix): string
    {
        return 'symfony_ai_model_catalog.'.hash('xxh128', $this->namespace ?? $this->catalog::class).'.'.$suffix;
    }
}

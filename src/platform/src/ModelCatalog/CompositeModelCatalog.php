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
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;

/**
 * Merges several model catalogs into one, returning the first match.
 *
 * Hand one to a provider to stack catalogs explicitly — e.g. a bridge's bundled
 * catalog plus your own custom models, or a models.dev-backed catalog for the long
 * tail. Catalogs are queried in order and the first one that knows a model wins, so
 * place the catalog whose definitions should take precedence first. Platform also
 * uses it internally to expose a unified view of all models across its providers.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CompositeModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: string, capabilities: list<Capability>}>|null
     */
    private ?array $mergedModels = null;

    /**
     * @param iterable<ModelCatalogInterface> $catalogs
     */
    public function __construct(
        private readonly iterable $catalogs,
    ) {
    }

    public function getModel(string $modelName): Model
    {
        foreach ($this->catalogs as $catalog) {
            try {
                return $catalog->getModel($modelName);
            } catch (ModelNotFoundException) {
                continue;
            }
        }

        throw new ModelNotFoundException(\sprintf('Model "%s" not found in any registered catalog.', $modelName));
    }

    /**
     * @return array<string, array{class: string, capabilities: list<Capability>}>
     */
    public function getModels(): array
    {
        if (null !== $this->mergedModels) {
            return $this->mergedModels;
        }

        $merged = [];
        foreach ($this->catalogs as $catalog) {
            $merged += $catalog->getModels();
        }

        return $this->mergedModels = $merged;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Model catalog powered by models.dev data.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param string                                                                    $providerId            The models.dev provider ID (e.g. "openai", "groq", "deepseek")
     * @param string|null                                                               $dataPath              Path to the models.dev JSON file (defaults to the bundled file)
     * @param array<string, array{class: class-string, capabilities: list<Capability>}> $additionalModels      Additional models to merge into the catalog
     * @param class-string|null                                                         $completionsModelClass Override the default completions model class
     * @param class-string|null                                                         $embeddingsModelClass  Override the default embeddings model class
     */
    public function __construct(
        string $providerId,
        ?string $dataPath = null,
        array $additionalModels = [],
        ?string $completionsModelClass = null,
        ?string $embeddingsModelClass = null,
    ) {
        $data = DataLoader::load($dataPath);

        if (!isset($data[$providerId])) {
            throw new InvalidArgumentException(\sprintf('Provider "%s" not found in models.dev data.', $providerId));
        }

        $completionsModelClass ??= CompletionsModel::class;
        $embeddingsModelClass ??= EmbeddingsModel::class;

        $models = [];
        foreach ($data[$providerId]['models'] as $modelData) {
            if ('deprecated' === ($modelData['status'] ?? 'active')) {
                continue;
            }

            $models[$modelData['id']] = [
                'class' => CapabilityMapper::isEmbeddingModel($modelData) ? $embeddingsModelClass : $completionsModelClass,
                'capabilities' => CapabilityMapper::map($modelData),
            ];
        }

        $this->models = array_merge($models, $additionalModels);
    }
}

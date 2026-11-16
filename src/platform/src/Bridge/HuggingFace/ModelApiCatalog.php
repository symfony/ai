<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Resolves models through the Hugging Face Hub API, with the capabilities of the configured inference provider.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelApiCatalog extends AbstractModelCatalog
{
    private const LISTING_LIMIT = 1000;

    private bool $modelsAreListed = false;

    /**
     * @var array<string, true>
     */
    private array $unknownModelIds = [];

    /**
     * @param ?string $provider Inference provider used for chat completion, any provider when null
     */
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly ?string $provider = null,
    ) {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        $modelId = explode(':', $this->parseModelName($modelName)['name'], 2)[0];

        if (!isset($this->models[$modelId])) {
            $this->models[$modelId] = $this->resolveModel($modelId);
        }

        return parent::getModel($modelName);
    }

    public function getModels(): array
    {
        if (!$this->modelsAreListed) {
            // Task pipelines run on hf-inference only, listed apart so they don't crowd chat models out of the limit
            $listedProviders = null === $this->provider ? ['all'] : array_unique([$this->provider, Provider::HF_INFERENCE]);

            foreach ($listedProviders as $listedProvider) {
                $filters = ['inference_provider' => $listedProvider, 'sort' => 'downloads'];

                foreach ($this->apiClient->listModels($filters, self::LISTING_LIMIT) as $hubModel) {
                    $definition = ModelMapper::map($hubModel, $this->provider);

                    if (null !== $definition) {
                        $this->models[$hubModel['id']] ??= $definition;
                    }
                }
            }

            $this->modelsAreListed = true;
        }

        return $this->models;
    }

    /**
     * @return array{class: class-string<Model>, capabilities: list<Capability>}
     */
    private function resolveModel(string $modelId): array
    {
        $definition = null;

        // routers probe every provider, so unknown models are asked for on each invocation
        if (!isset($this->unknownModelIds[$modelId])) {
            $hubModel = $this->apiClient->findModel($modelId);
            $definition = null !== $hubModel ? ModelMapper::map($hubModel, $this->provider) : null;
        }

        if (null === $definition) {
            $this->unknownModelIds[$modelId] = true;

            $scope = null === $this->provider ? '' : \sprintf(' on provider "%s"', $this->provider);

            throw new ModelNotFoundException(\sprintf('Model "%s" not found: it is not served for a supported task%s.', $modelId, $scope));
        }

        return $definition;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelApiCatalog extends AbstractOpenRouterModelCatalog
{
    protected bool $modelsAreLoaded = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    public function getModel(string $modelName): Model
    {
        $this->preloadRemoteModels();

        return parent::getModel($modelName);
    }

    public function getModels(): array
    {
        $this->preloadRemoteModels();

        return parent::getModels();
    }

    protected function preloadRemoteModels(): void
    {
        if (!$this->modelsAreLoaded) {
            $this->models = [
                ...$this->models,
                ...$this->fetchRemoteModels(),
                ...$this->fetchRemoteEmbeddings(),
            ];
            $this->modelsAreLoaded = true;
        }
    }

    /**
     * @return iterable<string, array{class: class-string<Model>, capabilities: list<Capability::*>}>
     */
    protected function fetchRemoteModels(): iterable
    {
        $responseModels = $this->httpClient->request('GET', 'https://openrouter.ai/api/v1/models');
        foreach ($responseModels->toArray()['data'] as $model) {
            $capabilities = [];

            foreach ($model['architecture']['input_modalities'] as $inputModality) {
                switch ($inputModality) {
                    case 'text':
                        $capabilities[] = Capability::INPUT_TEXT;
                        break;
                    case 'image':
                        $capabilities[] = Capability::INPUT_IMAGE;
                        break;
                    case 'audio':
                        $capabilities[] = Capability::INPUT_AUDIO;
                        break;
                    case 'file':
                        $capabilities[] = Capability::INPUT_PDF;
                        break;
                    case 'video':
                        $capabilities[] = Capability::INPUT_MULTIMODAL; // Video?
                        break;
                    default:
                        throw new InvalidArgumentException('Unknown model '.$inputModality.' input modality.', 1763717587);
                }
            }

            foreach ($model['architecture']['output_modalities'] as $outputModality) {
                switch ($outputModality) {
                    case 'text':
                        $capabilities[] = Capability::OUTPUT_TEXT;
                        break;
                    case 'image':
                        $capabilities[] = Capability::OUTPUT_IMAGE;
                        break;
                    default:
                        throw new InvalidArgumentException('Unknown model '.$outputModality.' output modality.', 1763717588);
                }
            }

            yield $model['id'] => [
                'class' => CompletionsModel::class,
                'capabilities' => $capabilities,
            ];
        }
    }

    /**
     * @return iterable<string, array{class: class-string<EmbeddingsModel>, capabilities: list<Capability::*>}>
     */
    protected function fetchRemoteEmbeddings(): iterable
    {
        $responseEmbeddings = $this->httpClient->request('GET', 'https://openrouter.ai/api/v1/embeddings/models');
        foreach ($responseEmbeddings->toArray()['data'] as $embedding) {
            yield $embedding['id'] => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ];
        }
    }
}

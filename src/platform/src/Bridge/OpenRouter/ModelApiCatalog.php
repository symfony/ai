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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelApiCatalog extends FallbackModelCatalog
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();

        // Models are fetched via OpenRouter meta API
        $this->models = $this->fetchRemoteModels();
    }

    protected function fetchRemoteModels(): array
    {
        // Rework based on Olama Model API

        $fullResult = [];

        $serializer = new Serializer(encoders: [new JsonEncoder()]);

        // Fetch models
        $responseModels = $this->httpClient->request('GET', 'https://openrouter.ai/api/v1/models');
        $models = $serializer->decode($responseModels->getContent(), 'json');

        foreach ($models['data'] as $model) {
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
                        throw new \Exception('Unknown model input modality', ['inputModality' => $inputModality]);
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
                        throw new \Exception('Unknown model output modality', ['outputModality' => $outputModality]);
                }
            }
            $fullResult[$model['id']] = [
                'class' => Model::class,
                'capabilities' => $capabilities,
            ];
        }

        // Fetch Embeddings
        $responseEmbeddings = $this->httpClient->request('GET', 'https://openrouter.ai/api/v1/embeddings/models');
        $embeddings = $serializer->decode($responseEmbeddings->getContent(), 'json');
        foreach ($embeddings['data'] as $embedding) {
            $fullResult[$embedding['id']] = [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ];
        }

        return $fullResult;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class OllamaApiCatalog extends FallbackModelCatalog
{
    public function __construct(
        private readonly string $host,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    public function getModel(string $modelName): Ollama
    {
        $model = parent::getModel($modelName);

        if (\array_key_exists($model->getName(), $this->models)) {
            $finalModel = $this->models[$model->getName()];

            return new $finalModel['class'](
                $model->getName(),
                $finalModel['capabilities'],
                $model->getOptions(),
            );
        }

        $response = $this->httpClient->request('POST', \sprintf('%s/api/show', $this->host), [
            'json' => [
                'model' => $model->getName(),
            ],
        ]);

        $payload = $response->toArray();

        if ([] === $payload['capabilities'] ?? []) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        $capabilities = array_map(
            static fn (string $capability): Capability => match ($capability) {
                'embedding' => Capability::EMBEDDINGS,
                'completion' => Capability::INPUT_TEXT,
                'tools' => Capability::TOOL_CALLING,
                'thinking' => Capability::THINKING,
                'vision' => Capability::INPUT_IMAGE,
                default => throw new InvalidArgumentException(\sprintf('The "%s" capability is not supported', $capability)),
            },
            $payload['capabilities'],
        );

        $finalModel = new Ollama($model->getName(), $capabilities, $model->getOptions());

        $this->models[$finalModel->getName()] = [
            'class' => Ollama::class,
            'capabilities' => $finalModel->getCapabilities(),
        ];

        return $finalModel;
    }
}

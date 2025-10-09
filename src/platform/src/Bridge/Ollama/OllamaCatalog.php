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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\DynamicModelCatalog;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OllamaCatalog extends DynamicModelCatalog
{
    public function __construct(
        private readonly string $host,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    public function getModel(string $modelName): Model
    {
        $model = parent::getModel($modelName);

        $response = $this->httpClient->request('POST', \sprintf('%s/api/show', $this->host), [
            'json' => [
                'model' => $model->getName(),
            ],
        ]);

        $payload = $response->toArray();

        if ([] === $payload['capabilities'] ?? []) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        return new Ollama($model->getName(), $payload['capabilities'], $model->getOptions());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelClient;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Model\EmbeddingsModel;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This default implementation is based on OpenAI's initial embeddings endpoint, that got later adopted by other
 * providers as well. It can be used by any bridge or directly with the default PlatformFactory.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class EmbeddingsModelClient implements ModelClientInterface
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $path = '/v1/embeddings',
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof EmbeddingsModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$this->path, [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }
}

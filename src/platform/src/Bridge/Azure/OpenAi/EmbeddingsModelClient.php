<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\OpenAi;

use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingsModelClient implements ModelClientInterface
{
    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $deployment,
        private readonly string $apiVersion,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        if (str_starts_with($this->baseUrl, 'http://')) {
            throw new InvalidArgumentException('The base URL must not contain the protocol.');
        }
        if (str_starts_with($this->baseUrl, 'https://')) {
            throw new InvalidArgumentException('The base URL must not contain the protocol.');
        }
        if ('' === $deployment) {
            throw new InvalidArgumentException('The deployment must not be empty.');
        }
        if ('' === $apiVersion) {
            throw new InvalidArgumentException('The API version must not be empty.');
        }
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $url = \sprintf('https://%s/openai/deployments/%s/embeddings', $this->baseUrl, $this->deployment);

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'api-key' => $this->apiKey,
            ],
            'query' => ['api-version' => $this->apiVersion],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }
}

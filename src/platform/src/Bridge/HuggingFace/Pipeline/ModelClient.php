<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Pipeline;

use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings;
use Symfony\AI\Platform\Bridge\HuggingFace\Pipeline;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of the Hugging Face inference router, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://router.huggingface.co',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Pipeline;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $url = \sprintf('%s/hf-inference/models/%s', $this->baseUrl, $model->getName());

        // Sentence similarity models only return embeddings on the feature extraction pipeline route
        if ($model instanceof Embeddings) {
            $url .= '/pipeline/feature-extraction';
        }

        if (\is_array($payload) && isset($payload['body'])) {
            return new RawHttpResult($this->httpClient->request('POST', $url, [
                'auth_bearer' => $this->apiKey,
                'headers' => $payload['headers'] ?? [],
                'body' => $payload['body'],
            ]));
        }

        $body = ['inputs' => $payload];
        if ([] !== $options) {
            $body['parameters'] = $options;
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody($body),
        ]));
    }
}

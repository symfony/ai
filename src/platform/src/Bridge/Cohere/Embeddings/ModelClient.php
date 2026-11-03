<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Embeddings;

use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\InputType;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Cohere-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.cohere.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        $texts = \is_array($payload) ? $payload : [$payload];

        $body = [
            'model' => $model->getName(),
            'texts' => $texts,
            'input_type' => ($options['input_type'] ?? $model->getOptions()['input_type'] ?? InputType::SearchDocument)->value,
        ];

        if (isset($options['embedding_types'])) {
            $body['embedding_types'] = $options['embedding_types'];
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v2/embed', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ] + $httpOptions));
    }
}

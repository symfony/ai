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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $texts = \is_array($payload) ? $payload : [$payload];

        $body = [
            'model' => $model->getName(),
            'texts' => $texts,
            'input_type' => $options['input_type'] ?? $model->getOptions()['input_type'] ?? Embeddings::INPUT_TYPE_SEARCH_DOCUMENT,
        ];

        if (isset($options['embedding_types'])) {
            $body['embedding_types'] = $options['embedding_types'];
        }

        return new RawHttpResult($this->httpClient->request('POST', 'https://api.cohere.com/v2/embed', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]));
    }
}

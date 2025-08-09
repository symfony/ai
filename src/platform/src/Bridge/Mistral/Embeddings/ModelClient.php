<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Embeddings;

use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings;
use Symfony\AI\Platform\Exception\InvalidActionArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ModelClient implements ModelClientInterface
{
    private EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter]
        private string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model, Action $action): bool
    {
        if (Action::CALCULATE_EMBEDDINGS !== $action) {
            return false;
        }

        return $model instanceof Embeddings;
    }

    public function request(Model $model, Action $action, array|string $payload, array $options = []): RawHttpResult
    {
        if (Action::CALCULATE_EMBEDDINGS !== $action) {
            return throw new InvalidActionArgumentException($model, $action, [Action::CALCULATE_EMBEDDINGS]);
        }

        return new RawHttpResult($this->httpClient->request('POST', 'https://api.mistral.ai/v1/embeddings', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }
}

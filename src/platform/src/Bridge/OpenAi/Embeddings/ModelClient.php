<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Exception\InvalidActionArgumentException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface as PlatformResponseFactory;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ModelClient implements PlatformResponseFactory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter]
        private string $apiKey,
    ) {
        '' !== $apiKey || throw new InvalidArgumentException('The API key must not be empty.');
        str_starts_with($apiKey, 'sk-') || throw new InvalidArgumentException('The API key must start with "sk-".');
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

        return new RawHttpResult($this->httpClient->request('POST', 'https://api.openai.com/v1/embeddings', [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }
}

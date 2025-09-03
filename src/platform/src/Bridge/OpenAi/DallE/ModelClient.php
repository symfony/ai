<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\DallE;

use Symfony\AI\Platform\Bridge\OpenAi\DallE;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://platform.openai.com/docs/api-reference/images/create
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final readonly class ModelClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
    ) {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }
        if (!str_starts_with($apiKey, 'sk-')) {
            throw new InvalidArgumentException('The API key must start with "sk-".');
        }
    }

    public function supports(Model $model): bool
    {
        return $model instanceof DallE;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'prompt' => $payload,
            ]),
        ]));
    }
}

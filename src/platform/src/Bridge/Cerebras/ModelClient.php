<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        if (!str_starts_with($apiKey, 'csk-')) {
            throw new InvalidArgumentException('The API key must start with "csk-".');
        }

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(BaseModel $model): bool
    {
        return $model instanceof Model;
    }

    public function request(BaseModel $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult(
            $this->httpClient->request(
                'POST', 'https://api.cerebras.ai/v1/chat/completions',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => \sprintf('Bearer %s', $this->apiKey),
                    ],
                    'json' => \is_array($payload) ? array_merge($payload, $options) : $payload,
                ]
            )
        );
    }
}

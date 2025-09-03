<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Gpt;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface as PlatformResponseFactory;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ModelClient implements PlatformResponseFactory
{
    private EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }
        if (!str_starts_with($apiKey, 'sk-')) {
            throw new InvalidArgumentException('The API key must start with "sk-".');
        }
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gpt;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge($options, $payload),
        ]));
    }
}

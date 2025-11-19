<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $provider,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return true;
    }

    /**
     * The difference in HuggingFace here is that we treat the payload as the options for the request not only the body.
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $provider = $options['provider'] ?? $this->provider;
        $task = $options['task'] ?? null;
        unset($options['task'], $options['provider']);

        return new RawHttpResult($this->httpClient->request('POST', $this->getUrl($model, $provider, $task), [
            'auth_bearer' => $this->apiKey,
            ...$this->getPayload($payload, $options),
        ]));
    }

    private function getUrl(Model $model, string $provider, ?string $task): string
    {
        $endpoint = Task::FEATURE_EXTRACTION === $task ? 'pipeline/feature-extraction' : 'models';
        $url = \sprintf('https://router.huggingface.co/%s/%s/%s', $provider, $endpoint, $model->getName());

        if (Task::CHAT_COMPLETION === $task) {
            $url .= '/v1/chat/completions';
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function getPayload(array|string $payload, array $options): array
    {
        // Expect JSON input if string or not
        if (\is_string($payload) || !(isset($payload['body']) || isset($payload['json']))) {
            $payload = ['json' => ['inputs' => $payload]];

            if ([] !== $options) {
                $payload['json']['parameters'] = $options;
            }
        }

        // Merge options into JSON payload
        if (isset($payload['json'])) {
            $payload['json'] = array_merge($payload['json'], $options);
        }

        $payload['headers'] ??= ['Content-Type' => 'application/json'];

        return $payload;
    }
}

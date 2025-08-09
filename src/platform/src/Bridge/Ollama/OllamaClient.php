<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Exception\InvalidActionArgumentException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class OllamaClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $hostUrl,
    ) {
    }

    public function supports(Model $model, Action $action): bool
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/api/show', $this->hostUrl), [
            'json' => [
                'model' => $model->getName(),
            ],
        ]);

        $capabilities = $response->toArray()['capabilities'] ?? null;

        if (null === $capabilities) {
            return false;
        }

        return match (true) {
            \in_array('completion', $capabilities, true) => Action::CHAT === $action,
            \in_array('embedding', $capabilities, true) => Action::CALCULATE_EMBEDDINGS === $action,
            default => false,
        };
    }

    public function request(Model $model, Action $action, array|string $payload, array $options = []): RawHttpResult
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/api/show', $this->hostUrl), [
            'json' => [
                'model' => $model->getName(),
            ],
        ]);

        $capabilities = $response->toArray()['capabilities'] ?? null;

        if (null === $capabilities) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        return match (true) {
            \in_array('completion', $capabilities, true) => $this->doCompletionRequest($payload, $options),
            \in_array('embedding', $capabilities, true) => $this->doEmbeddingsRequest($model, $payload, $options),
            default => throw new InvalidActionArgumentException($model, $action, [Action::CHAT, Action::COMPLETE_CHAT, Action::CALCULATE_EMBEDDINGS], new InvalidArgumentException(\sprintf('Unsupported model "%s": "%s".', $model::class, $model->getName()))),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doCompletionRequest(array|string $payload, array $options = []): RawHttpResult
    {
        // Revert Ollama's default streaming behavior
        $options['stream'] ??= false;

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/api/chat', $this->hostUrl), [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, $payload),
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    public function doEmbeddingsRequest(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/api/embed', $this->hostUrl), [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }
}

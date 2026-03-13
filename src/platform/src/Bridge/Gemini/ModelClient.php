<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Roy Garrido
 */
final class ModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gemini;
    }

    /**
     * @throws TransportExceptionInterface {@see self::handleContextGeneration()}
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return match (true) {
            !$model->supports(Capability::EMBEDDINGS) => $this->handleContextGeneration($model, $payload, $options),
            $model->supports(Capability::CACHE) && $options['cache'] ?? false => $this->handleContextCachingGeneration($model, $payload, $options),
            $model->supports(Capability::EMBEDDINGS) => $this->handleEmbeddingsGeneration($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('Model "%s" is not supported.', $model->getName())),
        };
    }

    /**
     * @throws TransportExceptionInterface When the HTTP request fails due to network issues
     */
    private function handleContextGeneration(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $options['responseMimeType'] = 'application/json';
            $options['responseJsonSchema'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'];
            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $generationConfig = ['generationConfig' => $options];
        unset($generationConfig['generationConfig']['stream']);
        unset($generationConfig['generationConfig']['tools']);
        unset($generationConfig['generationConfig']['server_tools']);

        if ([] === $generationConfig['generationConfig']) {
            $generationConfig = [];
        }

        if (isset($options['tools'])) {
            $generationConfig['tools'][] = ['functionDeclarations' => $options['tools']];
            unset($options['tools']);
        }

        foreach ($options['server_tools'] ?? [] as $tool => $params) {
            if (!$params) {
                continue;
            }

            $generationConfig['tools'][] = [$tool => true === $params ? new \ArrayObject() : $params];
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('models/%s:%s', $model->getName(), $options['stream'] ?? false ? 'streamGenerateContent' : 'generateContent'), [
            'json' => [
                ...$generationConfig,
                ...$payload,
            ],
        ]));
    }

    private function handleContextCachingGeneration(Model $model, array|string $payload, array $options): RawHttpResult
    {
    }

    private function handleEmbeddingsGeneration(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $modelOptions = $model->getOptions();

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('models/%s:%s', $model->getName(), $options['async'] ?? false ? 'asyncBatchEmbedContent' : 'batchEmbedContents'), [
            'json' => [
                'requests' => array_map(
                    static fn (string $text): array => [
                        'model' => \sprintf('models/%s', $model->getName()),
                        'content' => [
                            'parts' => [
                                ['text' => $text],
                            ],
                        ],
                        'outputDimensionality' => $modelOptions['dimensions'] ?? null,
                        'taskType' => $modelOptions['task_type'] ?? null,
                        'title' => $options['title'] ?? null,
                    ],
                    \is_array($payload) ? $payload : [$payload],
                ),
            ],
        ]));
    }
}

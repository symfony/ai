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
 * @author Valtteri R <valtzu@gmail.com>
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
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     *
     * @throws TransportExceptionInterface When the HTTP request fails due to network issues
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if ($model->supports(Capability::EMBEDDINGS)) {
            return $this->handleEmbeddingsGeneration($model, $payload, $options);
        }

        return $this->handleContextGeneration($model, $payload, $options);
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     *
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

        $config = ['generationConfig' => $options];
        unset($config['generationConfig']['stream']);
        unset($config['generationConfig']['tools']);
        unset($config['generationConfig']['tool_config']);
        unset($config['generationConfig']['server_tools']);

        if ([] === $config['generationConfig']) {
            $config = [];
        }

        if (isset($options['tools'])) {
            $config['tools'][] = ['functionDeclarations' => $options['tools']];
            unset($options['tools']);
        }

        if (isset($options['tool_config'])) {
            $config['tool_config'] = $options['tool_config'];
            unset($options['tool_config']);
        }

        foreach ($options['server_tools'] ?? [] as $tool => $params) {
            if (!$params) {
                continue;
            }

            $config['tools'][] = [$tool => true === $params ? new \ArrayObject() : $params];
        }

        $action = ($options['stream'] ?? false) ? 'streamGenerateContent' : 'generateContent';

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('models/%s:%s', $model->getName(), $action), [
            'json' => array_merge($config, $payload),
        ]));
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     *
     * @throws TransportExceptionInterface When the HTTP request fails due to network issues
     */
    private function handleEmbeddingsGeneration(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $modelOptions = $model->getOptions();
        $action = ($options['async'] ?? false) ? 'asyncBatchEmbedContent' : 'batchEmbedContents';

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('models/%s:%s', $model->getName(), $action), [
            'json' => [
                'requests' => array_map(
                    static fn (string $text): array => array_filter([
                        'model' => \sprintf('models/%s', $model->getName()),
                        'content' => [
                            'parts' => [
                                ['text' => $text],
                            ],
                        ],
                        'outputDimensionality' => $modelOptions['dimensions'] ?? null,
                        'taskType' => $modelOptions['task_type'] ?? null,
                        'title' => $options['title'] ?? null,
                    ]),
                    \is_array($payload) ? $payload : [$payload],
                ),
            ],
        ]));
    }
}

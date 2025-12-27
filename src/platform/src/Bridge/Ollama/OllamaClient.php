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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class OllamaClient implements ModelClientInterface
{
    private const CHAT_TOP_LEVEL_KEYS = [
        'stream',
        'format',
        'keep_alive',
        'tools',
        'think',
        'logprobs',
        'top_logprobs',
    ];

    private const EMBED_TOP_LEVEL_KEYS = [
        'truncate',
        'keep_alive',
        'dimensions',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $hostUrl,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Ollama;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return match (true) {
            $model->supports(Capability::INPUT_MESSAGES) => $this->doCompletionRequest($payload, $options),
            $model->supports(Capability::EMBEDDINGS) => $this->doEmbeddingsRequest($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('Unsupported model "%s": "%s".', $model::class, $model->getName())),
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

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $options['format'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'];
            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $options = $this->normalizeOllamaOptions($options, self::CHAT_TOP_LEVEL_KEYS);

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/api/chat', $this->hostUrl), [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, $payload),
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doEmbeddingsRequest(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $options = self::normalizeOllamaOptions($options, self::EMBED_TOP_LEVEL_KEYS);

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/api/embed', $this->hostUrl), [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string>        $topLevelKeys
     *
     * @return array<string, mixed>
     */
    private static function normalizeOllamaOptions(array $options, array $topLevelKeys): array
    {
        $topLevelOptions = [];
        $nested = [];

        if (isset($options['options']) && \is_array($options['options'])) {
            $nested = $options['options'];
        }

        foreach ($options as $key => $value) {
            if (\in_array($key, $topLevelKeys, true)) {
                $topLevelOptions[$key] = $value;
            } else {
                $nested[$key] ??= $value;
            }
        }

        if ([] !== $nested) {
            $topLevelOptions['options'] = $nested;
        }

        return $topLevelOptions;
    }
}

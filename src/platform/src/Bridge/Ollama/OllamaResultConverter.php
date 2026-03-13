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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-type ToolCallData array{function: array{name: string, arguments: array<string, mixed>}}
 * @phpstan-type CompletionMessage array{content?: string, tool_calls?: list<ToolCallData>}
 * @phpstan-type CompletionResponse array{message?: CompletionMessage}
 * @phpstan-type GenerationResponse array{response?: string}
 * @phpstan-type EmbeddingsResponse array{embeddings: array<int, list<float>>}
 * @phpstan-type StreamData array{message?: array{content?: string, thinking?: string, tool_calls?: list<ToolCallData>}, response?: string, done?: bool, prompt_eval_count?: int, eval_count?: int}
 */
final class OllamaResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Ollama;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (!$response instanceof ResponseInterface) {
            throw new InvalidArgumentException(\sprintf('Expected an instance of "%s", got "%s".', ResponseInterface::class, $response::class));
        }

        $url = $response->getInfo('url');

        if (!\is_string($url)) {
            throw new InvalidArgumentException('The response URL is not a string.');
        }

        $stream = $options['stream'] ?? false;

        if ($stream && (str_contains($url, 'chat') || str_contains($url, 'generate'))) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        return match (true) {
            str_contains($url, 'embed') && \array_key_exists('embeddings', $data) => $this->doConvertEmbeddings($data),
            str_contains($url, 'generate') => $this->convertGeneration($data),
            str_contains($url, 'chat') => $this->doConvertCompletion($data),
            default => throw new InvalidArgumentException('The requested resource cannot be processed, please check the Ollama API.'),
        };
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertGeneration(array $data): ResultInterface
    {
        if (!isset($data['response']) || !\is_string($data['response'])) {
            throw new RuntimeException('Response does not contain response.');
        }

        if ('' === $data['response']) {
            throw new RuntimeException('Response does not contain any data.');
        }

        return new TextResult($data['response']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function doConvertCompletion(array $data): ResultInterface
    {
        if (!isset($data['message']) || !\is_array($data['message'])) {
            throw new RuntimeException('Response does not contain message.');
        }

        $message = $data['message'];

        if (!isset($message['content']) || !\is_string($message['content'])) {
            throw new RuntimeException('Message does not contain content.');
        }

        $toolCalls = [];
        $rawToolCalls = $message['tool_calls'] ?? [];

        if (\is_array($rawToolCalls)) {
            /** @var array{function: array{name: string, arguments: array<string, mixed>}} $toolCall */
            foreach ($rawToolCalls as $id => $toolCall) {
                $toolCalls[] = new ToolCall((string) $id, $toolCall['function']['name'], $toolCall['function']['arguments']);
            }
        }

        if ([] !== $toolCalls) {
            return new ToolCallResult($toolCalls);
        }

        return new TextResult($message['content']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function doConvertEmbeddings(array $data): ResultInterface
    {
        if (!isset($data['embeddings']) || !\is_array($data['embeddings']) || [] === $data['embeddings']) {
            throw new RuntimeException('Response does not contain embeddings.');
        }

        /** @var array<int, list<float>> $embeddings */
        $embeddings = $data['embeddings'];

        return new VectorResult(array_map(
            static fn (array $embedding): VectorInterface => new Vector($embedding),
            $embeddings,
        ));
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        foreach ($result->getDataStream() as $data) {
            if ($this->streamIsToolCall($data)) {
                $toolCalls = $this->convertStreamToToolCalls($toolCalls, $data);
            }

            $message = isset($data['message']) && \is_array($data['message']) ? $data['message'] : [];

            if ($this->hasThinkingDelta($data)) {
                yield new ThinkingDelta(\is_string($message['thinking'] ?? null) ? $message['thinking'] : '');
            }

            if ($this->hasTextDelta($data)) {
                $content = $message['content'] ?? $data['response'] ?? null;

                if (!\is_string($content)) {
                    throw new InvalidArgumentException('Response does not contain text delta.');
                }

                yield new TextDelta($content);
            }

            if ([] !== $toolCalls && $this->isToolCallsStreamFinished($data)) {
                yield new ToolCallComplete($toolCalls);
            }

            if ($this->hasStreamTokenUsage($data)) {
                yield new TokenUsage(
                    promptTokens: isset($data['prompt_eval_count']) && \is_int($data['prompt_eval_count']) ? $data['prompt_eval_count'] : null,
                    completionTokens: isset($data['eval_count']) && \is_int($data['eval_count']) ? $data['eval_count'] : null,
                );
            }
        }
    }

    /**
     * @param list<ToolCall>       $toolCalls
     * @param array<string, mixed> $data
     *
     * @return list<ToolCall>
     */
    private function convertStreamToToolCalls(array $toolCalls, array $data): array
    {
        if (!isset($data['message']) || !\is_array($data['message']) || !isset($data['message']['tool_calls']) || !\is_array($data['message']['tool_calls'])) {
            return $toolCalls;
        }

        /** @var array{function: array{name: string, arguments: array<string, mixed>}} $toolCall */
        foreach ($data['message']['tool_calls'] as $id => $toolCall) {
            $toolCalls[] = new ToolCall((string) $id, $toolCall['function']['name'], $toolCall['function']['arguments']);
        }

        return $toolCalls;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function streamIsToolCall(array $data): bool
    {
        return \is_array($data['message'] ?? null) && isset($data['message']['tool_calls']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isToolCallsStreamFinished(array $data): bool
    {
        return isset($data['done']) && true === $data['done'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasStreamTokenUsage(array $data): bool
    {
        return isset($data['prompt_eval_count'], $data['eval_count']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasTextDelta(array $data): bool
    {
        $message = $data['message'] ?? null;

        return \is_array($message) && isset($message['content']) && '' !== $message['content'] || isset($data['response']) && '' !== $data['response'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasThinkingDelta(array $data): bool
    {
        $message = $data['message'] ?? null;

        return \is_array($message) && isset($message['thinking']) && '' !== $message['thinking'];
    }
}

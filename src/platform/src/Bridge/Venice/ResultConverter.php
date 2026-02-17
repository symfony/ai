<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
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
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Venice;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        $rawUrl = $response->getInfo('url');

        if (!\is_string($rawUrl)) {
            throw new RuntimeException('Expected URL info to be a string.');
        }

        if (str_contains($rawUrl, 'embeddings')) {
            $payload = $response->toArray();
            $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];

            if ([] === $data) {
                throw new InvalidArgumentException('No embeddings found in the response.');
            }

            return new VectorResult(array_map(
                static function (mixed $entry): VectorInterface {
                    if (!\is_array($entry) || !\is_array($entry['embedding'] ?? null)) {
                        throw new InvalidArgumentException('Expected embedding to be an array.');
                    }

                    return new Vector(array_map(
                        static function (mixed $v): float {
                            if (!\is_float($v) && !\is_int($v)) {
                                throw new InvalidArgumentException('Expected embedding value to be a number.');
                            }

                            return (float) $v;
                        },
                        array_values($entry['embedding']),
                    ));
                },
                $data,
            ));
        }

        if (str_contains($rawUrl, 'completions') && ($options['stream'] ?? false)) {
            return new StreamResult($this->convertCompletionToGenerator($result));
        }

        if (str_contains($rawUrl, 'completions')) {
            $payload = $response->toArray();
            $choices = \is_array($payload['choices'] ?? null) ? $payload['choices'] : [];
            $firstChoice = \is_array($choices[0] ?? null) ? $choices[0] : [];
            $message = \is_array($firstChoice['message'] ?? null) ? $firstChoice['message'] : [];

            if (\is_array($message['tool_calls'] ?? null) && [] !== $message['tool_calls']) {
                /** @var list<array<string, mixed>> $toolCalls */
                $toolCalls = array_values($message['tool_calls']);

                return $this->convertToolCalls($toolCalls);
            }

            if (!\is_string($message['content'] ?? null) || '' === $message['content']) {
                throw new InvalidArgumentException('No completions found in the response.');
            }

            return new TextResult($message['content']);
        }

        if (str_contains($rawUrl, 'audio/speech')) {
            return new BinaryResult($response->getContent());
        }

        if (str_contains($rawUrl, 'image/generate') || str_contains($rawUrl, 'image/upscale') || str_contains($rawUrl, 'image/edit') || str_contains($rawUrl, 'image/multi-edit') || str_contains($rawUrl, 'image/background-remove')) {
            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';

            if (!str_contains($contentType, 'application/json')) {
                return new BinaryResult($response->getContent());
            }

            $payload = $response->toArray();
            $images = \is_array($payload['images'] ?? null) ? $payload['images'] : [];

            if ([] === $images) {
                throw new InvalidArgumentException('No images found in the response.');
            }

            if (1 < \count($images)) {
                return new ChoiceResult(array_map(
                    static function (mixed $imageAsBase64): BinaryResult {
                        if (!\is_string($imageAsBase64)) {
                            throw new InvalidArgumentException('Expected image data to be a base64 string.');
                        }

                        return new BinaryResult(base64_decode($imageAsBase64));
                    },
                    array_values($images),
                ));
            }

            if (!\is_string($images[0])) {
                throw new InvalidArgumentException('Expected image data to be a base64 string.');
            }

            return new BinaryResult(base64_decode($images[0]));
        }

        if (str_contains($rawUrl, 'video/retrieve')) {
            return new BinaryResult($response->getContent());
        }

        if (str_contains($rawUrl, 'transcription')) {
            $transcription = $response->toArray();

            if (!\is_string($transcription['text'] ?? null)) {
                throw new InvalidArgumentException('No transcription text found in the response.');
            }

            return new TextResult($transcription['text']);
        }

        throw new RuntimeException('Unsupported model capability.');
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param list<array<string, mixed>> $toolCalls
     */
    private function convertToolCalls(array $toolCalls): ToolCallResult
    {
        $calls = [];

        foreach ($toolCalls as $toolCall) {
            $id = \is_string($toolCall['id'] ?? null) ? $toolCall['id'] : '';
            $function = \is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];
            $name = \is_string($function['name'] ?? null) ? $function['name'] : '';
            $arguments = $function['arguments'] ?? '{}';

            if (\is_string($arguments)) {
                $decoded = json_decode($arguments, true);
                $arguments = \is_array($decoded) ? $decoded : [];
            }

            if (!\is_array($arguments)) {
                $arguments = [];
            }

            /** @var array<string, mixed> $typedArguments */
            $typedArguments = $arguments;

            $calls[] = new ToolCall($id, $name, $typedArguments);
        }

        return new ToolCallResult($calls);
    }

    private function convertCompletionToGenerator(RawResultInterface $result): \Generator
    {
        $accumulatedToolCalls = [];

        foreach ($result->getDataStream() as $chunk) {
            if (!\is_array($chunk)) {
                continue;
            }

            $choices = $chunk['choices'] ?? [];

            if (\is_array($choices) && [] !== $choices) {
                $firstChoice = $choices[0] ?? [];

                if (\is_array($firstChoice)) {
                    $delta = \is_array($firstChoice['delta'] ?? null) ? $firstChoice['delta'] : [];

                    $content = $delta['content'] ?? null;
                    if (\is_string($content) && '' !== $content) {
                        yield new TextDelta($content);
                    }

                    $reasoning = $delta['reasoning_content'] ?? $delta['reasoning'] ?? null;
                    if (\is_string($reasoning) && '' !== $reasoning) {
                        yield new ThinkingDelta($reasoning);
                    }

                    if (\is_array($delta['tool_calls'] ?? null)) {
                        foreach ($delta['tool_calls'] as $toolCall) {
                            if (!\is_array($toolCall)) {
                                continue;
                            }

                            $index = \is_int($toolCall['index'] ?? null) ? $toolCall['index'] : 0;

                            if (!isset($accumulatedToolCalls[$index])) {
                                $accumulatedToolCalls[$index] = ['id' => '', 'name' => '', 'arguments' => ''];
                            }

                            if (\is_string($toolCall['id'] ?? null)) {
                                $accumulatedToolCalls[$index]['id'] = $toolCall['id'];
                            }

                            $function = \is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];

                            if (\is_string($function['name'] ?? null)) {
                                $accumulatedToolCalls[$index]['name'] = $function['name'];
                            }

                            if (\is_string($function['arguments'] ?? null)) {
                                $accumulatedToolCalls[$index]['arguments'] .= $function['arguments'];
                            }
                        }
                    }
                }
            }

            $usage = $chunk['usage'] ?? null;

            if (\is_array($usage)) {
                $promptTokens = isset($usage['prompt_tokens']) && \is_int($usage['prompt_tokens']) ? $usage['prompt_tokens'] : null;
                $completionTokens = isset($usage['completion_tokens']) && \is_int($usage['completion_tokens']) ? $usage['completion_tokens'] : null;
                $totalTokens = isset($usage['total_tokens']) && \is_int($usage['total_tokens']) ? $usage['total_tokens'] : null;

                yield new TokenUsage(
                    promptTokens: $promptTokens,
                    completionTokens: $completionTokens,
                    totalTokens: $totalTokens,
                );
            }
        }

        if ([] !== $accumulatedToolCalls) {
            $calls = [];

            foreach ($accumulatedToolCalls as $partial) {
                $arguments = json_decode($partial['arguments'] ?: '{}', true);
                /** @var array<string, mixed> $arguments */
                $arguments = \is_array($arguments) ? $arguments : [];
                $calls[] = new ToolCall($partial['id'], $partial['name'], $arguments);
            }

            yield new ToolCallComplete($calls);
        }
    }
}

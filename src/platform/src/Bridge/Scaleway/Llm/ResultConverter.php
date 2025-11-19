<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway\Llm;

use Symfony\AI\Platform\Bridge\Scaleway\Scaleway;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Scaleway;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }
        $data = $result->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (!isset($data['choices'])) {
            throw new RuntimeException('Result does not contain choices.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult(...$choices);
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        foreach ($result->getDataStream() as $data) {
            if ($this->streamIsToolCall($data)) {
                $toolCalls = $this->convertStreamToToolCalls($toolCalls, $data);
            }

            if ([] !== $toolCalls && $this->isToolCallsStreamFinished($data)) {
                yield new ToolCallResult(...array_map($this->convertToolCall(...), $toolCalls));
            }

            if (!isset($data['choices'][0]['delta']['content'])) {
                continue;
            }

            yield $data['choices'][0]['delta']['content'];
        }
    }

    /**
     * @param array<string, mixed> $toolCalls
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function convertStreamToToolCalls(array $toolCalls, array $data): array
    {
        if (!isset($data['choices'][0]['delta']['tool_calls'])) {
            return $toolCalls;
        }

        foreach ($data['choices'][0]['delta']['tool_calls'] as $i => $toolCall) {
            if (isset($toolCall['id'])) {
                // initialize tool call
                $toolCalls[$i] = [
                    'id' => $toolCall['id'],
                    'function' => $toolCall['function'],
                ];
                continue;
            }

            // add arguments delta to tool call
            $toolCalls[$i]['function']['arguments'] .= $toolCall['function']['arguments'];
        }

        return $toolCalls;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function streamIsToolCall(array $data): bool
    {
        return isset($data['choices'][0]['delta']['tool_calls']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isToolCallsStreamFinished(array $data): bool
    {
        return isset($data['choices'][0]['finish_reason']) && 'tool_calls' === $data['choices'][0]['finish_reason'];
    }

    /**
     * @param array{
     *     index: int,
     *     message: array{
     *         role: 'assistant',
     *         content: ?string,
     *         tool_calls: array{
     *             id: string,
     *             type: 'function',
     *             function: array{
     *                 name: string,
     *                 arguments: string
     *             },
     *         },
     *         refusal: ?mixed
     *     },
     *     logprobs: string,
     *     finish_reason: 'stop'|'length'|'tool_calls'|'content_filter',
     * } $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult
    {
        if ('tool_calls' === $choice['finish_reason']) {
            return new ToolCallResult(...array_map([$this, 'convertToolCall'], $choice['message']['tool_calls']));
        }

        if (\in_array($choice['finish_reason'], ['stop', 'length'], true)) {
            return new TextResult($choice['message']['content']);
        }

        throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $choice['finish_reason']));
    }

    /**
     * @param array{
     *     id: string,
     *     type: 'function',
     *     function: array{
     *         name: string,
     *         arguments: string
     *     }
     * } $toolCall
     */
    private function convertToolCall(array $toolCall): ToolCall
    {
        $arguments = json_decode($toolCall['function']['arguments'], true, flags: \JSON_THROW_ON_ERROR);

        return new ToolCall($toolCall['id'], $toolCall['function']['name'], $arguments);
    }
}

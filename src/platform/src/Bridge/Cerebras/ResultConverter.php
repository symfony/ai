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

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(BaseModel $model): bool
    {
        return $model instanceof Model;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['type'], $data['message']) && str_ends_with($data['type'], 'error')) {
            throw new RuntimeException(\sprintf('Cerebras API error: "%s"', $data['message']));
        }

        if (!isset($data['choices'][0])) {
            throw new RuntimeException('Response does not contain output.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * @param array{
     *     index: int,
     *     message: array{
     *         role: 'assistant',
     *         content: ?string,
     *         tool_calls?: list<array{
     *             id: string,
     *             type: 'function',
     *             function: array{
     *                 name: string,
     *                 arguments: string
     *             },
     *         }>,
     *     },
     *     finish_reason: 'stop'|'length'|'tool_calls',
     * } $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult
    {
        if ('tool_calls' === $choice['finish_reason']) {
            return new ToolCallResult(...array_map($this->convertToolCall(...), $choice['message']['tool_calls']));
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
                $toolCalls[$i] = [
                    'id' => $toolCall['id'],
                    'function' => $toolCall['function'],
                ];
                continue;
            }

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
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Llm;

use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Cohere;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $code = $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $code, $httpResponse->getContent(false)));
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        $finishReason = $data['finish_reason'] ?? null;

        if ('COMPLETE' === $finishReason) {
            $text = $data['message']['content'][0]['text'] ?? '';

            return new TextResult($text);
        }

        if ('TOOL_CALL' === $finishReason) {
            return new ToolCallResult(array_map($this->convertToolCall(...), $data['message']['tool_calls'] ?? []));
        }

        throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $finishReason));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? null;

            if ('content-delta' === $type) {
                yield new TextDelta($data['delta']['message']['content']['text'] ?? '');
                continue;
            }

            if ('tool-call-start' === $type) {
                $toolCall = $data['delta']['message']['tool_calls'] ?? null;
                if (null !== $toolCall) {
                    $toolCalls[] = [
                        'id' => $toolCall['id'] ?? '',
                        'function' => [
                            'name' => $toolCall['function']['name'] ?? '',
                            'arguments' => $toolCall['function']['arguments'] ?? '',
                        ],
                    ];
                }
                continue;
            }

            if ('tool-call-delta' === $type) {
                if ([] !== $toolCalls) {
                    $lastIndex = \count($toolCalls) - 1;
                    $toolCalls[$lastIndex]['function']['arguments'] .= $data['delta']['message']['tool_calls']['function']['arguments'] ?? '';
                }
                continue;
            }

            if ('message-end' === $type && [] !== $toolCalls) {
                yield new ToolCallComplete(array_map($this->convertToolCall(...), $toolCalls));
            }
        }
    }

    /**
     * @param array{
     *     id: string,
     *     function: array{
     *         name: string,
     *         arguments: string
     *     }
     * } $toolCall
     */
    private function convertToolCall(array $toolCall): ToolCall
    {
        $argumentsJson = (string) $toolCall['function']['arguments'];
        $arguments = '' !== $argumentsJson
            ? json_decode($argumentsJson, true, flags: \JSON_THROW_ON_ERROR)
            : [];

        return new ToolCall($toolCall['id'], $toolCall['function']['name'], $arguments);
    }
}

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

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class OllamaResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Ollama;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        return \array_key_exists('embeddings', $data)
            ? $this->doConvertEmbeddings($data)
            : $this->doConvertCompletion($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function doConvertCompletion(array $data): ResultInterface
    {
        if (!isset($data['message'])) {
            throw new RuntimeException('Response does not contain message.');
        }

        if (!isset($data['message']['content'])) {
            throw new RuntimeException('Message does not contain content.');
        }

        $toolCalls = [];

        foreach ($data['message']['tool_calls'] ?? [] as $id => $toolCall) {
            $toolCalls[] = new ToolCall($id, $toolCall['function']['name'], $toolCall['function']['arguments']);
        }

        if ([] !== $toolCalls) {
            return new ToolCallResult(...$toolCalls);
        }

        return new TextResult($data['message']['content']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function doConvertEmbeddings(array $data): ResultInterface
    {
        if ([] === $data['embeddings']) {
            throw new RuntimeException('Response does not contain embeddings.');
        }

        return new VectorResult(
            ...array_map(
                static fn (array $embedding): Vector => new Vector($embedding),
                $data['embeddings'],
            ),
        );
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        foreach ($result->getDataStream() as $data) {
            if ($this->streamIsToolCall($data)) {
                $toolCalls = $this->convertStreamToToolCalls($toolCalls, $data);
            }

            if ([] !== $toolCalls && $this->isToolCallsStreamFinished($data)) {
                yield new ToolCallResult(...$toolCalls);
            }

            yield new OllamaMessageChunk(
                $data['model'],
                new \DateTimeImmutable($data['created_at']),
                $data['message'],
                $data['done'],
                $data,
            );
        }
    }

    /**
     * @param array<string, mixed> $toolCalls
     * @param array<string, mixed> $data
     *
     * @return array<ToolCall>
     */
    private function convertStreamToToolCalls(array $toolCalls, array $data): array
    {
        if (!isset($data['message']['tool_calls'])) {
            return $toolCalls;
        }

        foreach ($data['message']['tool_calls'] ?? [] as $id => $toolCall) {
            $toolCalls[] = new ToolCall($id, $toolCall['function']['name'], $toolCall['function']['arguments']);
        }

        return $toolCalls;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function streamIsToolCall(array $data): bool
    {
        return isset($data['message']['tool_calls']);
    }

    /**
     * @param array<string, mixed> $data^
     */
    private function isToolCallsStreamFinished(array $data): bool
    {
        return isset($data['done']) && true === $data['done'];
    }
}

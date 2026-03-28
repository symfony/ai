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
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\Component\String\UnicodeString;

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
        $url = new UnicodeString($result->getObject()->getInfo('url'));

        $stream = $options['stream'] ?? false;

        return match (true) {
            $url->containsAny('embed') && \array_key_exists('embeddings', $result->getData()) => new VectorResult(...array_map(
                static fn (array $embedding): VectorInterface => new Vector($embedding),
                $result->getData()['embeddings'],
            )),
            $url->containsAny('chat') && $stream => new StreamResult($this->convertStream($url, $result)),
            $url->containsAny('generate') && $stream => new StreamResult($this->convertStream($url, $result)),
            $url->containsAny('generate') => new TextResult($result->getData()['response']),
            $url->containsAny('chat') => $this->doConvertCompletion($result->getData()),
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
    private function doConvertCompletion(array $data): ResultInterface
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

    private function convertStream(UnicodeString $url, RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        foreach ($result->getDataStream() as $data) {
            if (isset($data['message']['tool_calls'])) {
                $toolCalls = $this->convertStreamToToolCalls($toolCalls, $data);
            }

            if ([] !== $toolCalls && isset($data['done']) && true === $data['done']) {
                yield new ToolCallResult(...$toolCalls);
            }

            yield new OllamaMessageChunk(
                $data['model'],
                new \DateTimeImmutable($data['created_at']),
                $url->containsAny('generate') ? $data['response'] : $data['message'],
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
}

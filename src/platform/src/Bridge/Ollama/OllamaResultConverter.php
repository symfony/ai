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
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class OllamaResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Ollama;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result->getObject()));
        }

        $data = $result->getData();

        return \array_key_exists('embeddings', $data)
            ? $this->doConvertEmbeddings($data)
            : $this->doConvertCompletion($data, $options);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function doConvertCompletion(array $data, array $options): ResultInterface
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

        $result = new TextResult($data['message']['content']);

        if (\array_key_exists('prompt_cache_key', $options)) {
            $metadata = $result->getMetadata();

            $metadata->add('cached', true);
            $metadata->add('prompt_cache_key', $options['prompt_cache_key']);
        }

        return $result;
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

    private function convertStream(ResponseInterface $result): \Generator
    {
        foreach ((new EventSourceHttpClient())->stream($result) as $chunk) {
            if ($chunk instanceof FirstChunk || $chunk instanceof LastChunk) {
                continue;
            }

            try {
                $data = json_decode($chunk->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new RuntimeException('Failed to decode JSON: '.$e->getMessage());
            }

            yield new OllamaMessageChunk(
                $data['model'],
                new \DateTimeImmutable($data['created_at']),
                $data['message'],
                $data['done'],
            );
        }
    }
}

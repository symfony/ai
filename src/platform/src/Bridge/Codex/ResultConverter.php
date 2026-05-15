<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Converts Codex CLI JSONL output into platform result objects.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Codex;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if ([] === $data) {
            throw new RuntimeException('Codex CLI did not return any result.');
        }

        if ('error' === ($data['type'] ?? '')) {
            throw new RuntimeException(\sprintf('Codex CLI error: "%s"', $data['message'] ?? 'Unknown error'));
        }

        $text = $data['item']['text'] ?? null;

        if (null === $text) {
            throw new RuntimeException('Codex CLI result does not contain a text field.');
        }

        $results = [];
        foreach ($data['tool_calls'] ?? [] as $toolCall) {
            $results[] = new ToolCallResult([new ToolCall(
                $toolCall['id'],
                $toolCall['name'],
                $toolCall['arguments'] ?? [],
            )]);
        }

        $results[] = new TextResult($text);

        if (1 === \count($results)) {
            return $results[0];
        }

        return new MultiPartResult($results);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';

            if ('item.completed' === $type
                && 'agent_message' === ($data['item']['type'] ?? '')
                && isset($data['item']['text'])
            ) {
                yield new TextDelta($data['item']['text']);
            }
        }
    }
}

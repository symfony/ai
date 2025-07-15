<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\ResultExtractor;

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\JsonPath;

/**
 * @phpstan-type ToolCallArray array{
 *     id: string,
 *     type: 'function',
 *     function: array{
 *         name: string,
 *         arguments: string
 *     }
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamResultExtractor
{
    /**
     * @var ToolCallArray[]
     */
    private array $toolCalls = [];

    public function __construct(
        private string|JsonPath $textPath = '$.choices[*].delta.content',
        private string|JsonPath $toolCallsPath = '$.choices[*].delta.tool_calls',
        private string|JsonPath $finishReasonPath = '$.choices[*].finish_reason',
    ) {
    }

    /**
     * @return iterable<string|ToolCallResult>
     */
    public function extract(JsonCrawler $crawler): iterable
    {
        if ([] !== $text = array_filter($crawler->find($this->textPath))) {
            yield from $text;
        }

        if ($this->streamIsToolCall($crawler)) {
            $this->convertStreamToToolCalls($crawler);
        }

        if ([] !== $this->toolCalls && $this->isToolCallsStreamFinished($crawler)) {
            yield new ToolCallResult(...array_map($this->convertToolCall(...), $this->toolCalls));
            $this->toolCalls = [];
        }
    }

    private function convertStreamToToolCalls(JsonCrawler $crawler): void
    {
        foreach ($crawler->find($this->toolCallsPath) as $choice) {
            foreach ($choice as $i => $toolCall) {
                if (isset($toolCall['id'])) {
                    // initialize tool call
                    $this->toolCalls[$i] = [
                        'id' => $toolCall['id'],
                        'function' => $toolCall['function'],
                    ];
                    continue;
                }

                // add arguments delta to tool call
                $this->toolCalls[$i]['function']['arguments'] .= $toolCall['function']['arguments'];
            }
        }
    }

    private function streamIsToolCall(JsonCrawler $crawler): bool
    {
        return [] !== $crawler->find($this->toolCallsPath);
    }

    private function isToolCallsStreamFinished(JsonCrawler $crawler): bool
    {
        return \in_array('tool_calls', $crawler->find($this->finishReasonPath), true);
    }

    /**
     * @param ToolCallArray $toolCall
     */
    private function convertToolCall(array $toolCall): ToolCall
    {
        $arguments = json_decode($toolCall['function']['arguments'], true, \JSON_THROW_ON_ERROR);

        return new ToolCall($toolCall['id'], $toolCall['function']['name'], $arguments);
    }
}

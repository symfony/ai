<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AmazeeAi;

use Symfony\AI\Platform\Bridge\Generic\Completions\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Completions ResultConverter for amazee.ai's LiteLLM proxy.
 *
 * LiteLLM may return finish_reason "tool_calls" for structured output
 * responses but place the content in message.content instead of
 * message.tool_calls. This converter handles that quirk by checking
 * for tool_calls first and falling back to message.content as TextResult.
 */
class CompletionsResultConverter extends ResultConverter
{
    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * Converts a choice, handling LiteLLM's quirk where finish_reason is
     * "tool_calls" but the actual content is in message.content (structured output)
     * instead of message.tool_calls.
     *
     * @param array<string, mixed> $choice
     */
    protected function convertChoice(array $choice): ToolCallResult|TextResult
    {
        if ('tool_calls' === $choice['finish_reason']) {
            if (isset($choice['message']['tool_calls'])) {
                return new ToolCallResult(array_map([$this, 'convertToolCall'], $choice['message']['tool_calls']));
            }

            // LiteLLM structured output: finish_reason is "tool_calls" but
            // content is in message.content instead of message.tool_calls
            if (isset($choice['message']['content'])) {
                return new TextResult($choice['message']['content']);
            }
        }

        if (\in_array($choice['finish_reason'], ['stop', 'length'], true)) {
            return new TextResult($choice['message']['content']);
        }

        throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $choice['finish_reason']));
    }
}

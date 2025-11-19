<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Anthropic;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Björn Altmann
 */
final class ClaudeResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Claude;
    }

    public function convert(RawResultInterface|RawBedrockResult $result, array $options = []): ToolCallResult|TextResult
    {
        $data = $result->getData();

        if (!isset($data['content']) || [] === $data['content']) {
            throw new RuntimeException('Response does not contain any content.');
        }

        if (!isset($data['content'][0]['text']) && !isset($data['content'][0]['type'])) {
            throw new RuntimeException('Response content does not contain any text or type.');
        }

        $toolCalls = [];
        foreach ($data['content'] as $content) {
            if ('tool_use' === $content['type']) {
                $toolCalls[] = new ToolCall($content['id'], $content['name'], $content['input']);
            }
        }
        if ([] !== $toolCalls) {
            return new ToolCallResult(...$toolCalls);
        }

        return new TextResult($data['content'][0]['text']);
    }
}

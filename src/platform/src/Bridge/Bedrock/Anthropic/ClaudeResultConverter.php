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
use Symfony\AI\Platform\Bridge\Anthropic\FinishReasonMapper;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Björn Altmann
 */
final class ClaudeResultConverter implements ResultConverterInterface
{
    use FinishReasonAwareTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Claude;
    }

    public function convert(RawResultInterface|RawBedrockResult $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        if (!isset($data['content']) || [] === $data['content']) {
            throw new RuntimeException('Response does not contain any content.');
        }

        $results = [];
        foreach ($data['content'] as $content) {
            $type = $content['type'] ?? null;

            if ('tool_use' === $type) {
                $results[] = new ToolCallResult([new ToolCall($content['id'], $content['name'], $content['input'])]);
            } elseif ('text' === $type) {
                $results[] = new TextResult($content['text']);
            } elseif ('thinking' === $type) {
                $results[] = new ThinkingResult($content['thinking'], $content['signature'] ?? null);
            }
        }

        if ([] === $results) {
            throw new RuntimeException('Response content does not contain any supported content.');
        }

        return $this->withFinishReason(
            1 === \count($results) ? $results[0] : new MultiPartResult($results),
            FinishReasonMapper::map($data['stop_reason'] ?? null),
        );
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}

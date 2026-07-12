<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Completions;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        if ($options['stream'] ?? false) {
            return null;
        }

        $content = $rawResult->getData();

        if (!\array_key_exists('usage', $content)) {
            return null;
        }

        return $this->extractFromArray($content['usage']);
    }

    /**
     * @param array<string, mixed> $usage
     */
    public function extractFromArray(array $usage): TokenUsage
    {
        $promptTokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null;
        $completionTokens = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? null;
        $totalTokens = $usage['total_tokens'] ?? null;
        $completionTokensDetails = \is_array($usage['completion_tokens_details'] ?? null) ? $usage['completion_tokens_details'] : [];
        $promptTokensDetails = \is_array($usage['prompt_tokens_details'] ?? null) ? $usage['prompt_tokens_details'] : [];

        // DeepSeek: usage.completion_tokens_details.reasoning_tokens
        $thinkingTokens = $completionTokensDetails['reasoning_tokens'] ?? null;

        // DeepSeek: usage.prompt_cache_hit_tokens
        // z.ai, llama.cpp, vLLM-compatible: usage.prompt_tokens_details.cached_tokens
        $cacheRead = $usage['prompt_cache_hit_tokens']
            ?? $promptTokensDetails['cached_tokens']
            ?? null;

        // num_cached_tokens / cached_tokens
        $aggregateCached = $usage['num_cached_tokens']
            ?? $usage['cached_tokens']
            ?? null;

        $effectiveCached = $aggregateCached ?? $cacheRead;

        return new TokenUsage(
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            thinkingTokens: $thinkingTokens,
            cachedTokens: $effectiveCached,
            cacheReadTokens: $cacheRead,
            totalTokens: $totalTokens,
        );
    }
}

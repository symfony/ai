<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DeepSeek;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        if ($options['stream'] ?? false) {
            // Streams have to be handled manually as the tokens are part of the streamed chunks
            return null;
        }

        $content = $rawResult->getData();

        if (!\array_key_exists('usage', $content)) {
            return null;
        }

        return $this->extractFromArray($content['usage'], $content['model'] ?? null);
    }

    /**
     * @param array<string, mixed> $usage
     */
    public function extractFromArray(array $usage, ?string $model = null): TokenUsage
    {
        return new TokenUsage(
            promptTokens: $usage['prompt_tokens'] ?? null,
            completionTokens: $usage['completion_tokens'] ?? null,
            cachedTokens: $usage['prompt_cache_hit_tokens'] ?? null,
            totalTokens: $usage['total_tokens'] ?? null,
            model: $model,
        );
    }
}

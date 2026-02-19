<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsage
    {
        if ($options['stream'] ?? false) {
            // Streams have to be handled manually as the tokens are part of the streamed chunks
            return null;
        }

        $content = $rawResult->getData();

        if (!\array_key_exists('usage', $content)) {
            return null;
        }

        $usage = $content['usage'];

        $cacheCreationTokens = isset($usage['cache_creation_input_tokens']) ? (int) $usage['cache_creation_input_tokens'] : null;
        $cacheReadTokens = isset($usage['cache_read_input_tokens']) ? (int) $usage['cache_read_input_tokens'] : null;

        // cachedTokens is the combined total for callers that only need a
        // single "how much was cached" number; the two breakdown fields carry
        // the individual read / creation counts for billing-aware consumers.
        $cachedTokens = (null !== $cacheCreationTokens || null !== $cacheReadTokens) ? ($cacheCreationTokens ?? 0) + ($cacheReadTokens ?? 0) : null;

        return new TokenUsage(
            promptTokens: $usage['input_tokens'] ?? null,
            completionTokens: $usage['output_tokens'] ?? null,
            toolTokens: $usage['server_tool_use']['web_search_requests'] ?? null,
            cachedTokens: $cachedTokens,
            cacheCreationTokens: $cacheCreationTokens,
            cacheReadTokens: $cacheReadTokens,
        );
    }
}

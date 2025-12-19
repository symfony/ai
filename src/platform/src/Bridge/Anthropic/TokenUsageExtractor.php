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
        $cachedTokens = null;
        if (\array_key_exists('cache_creation_input_tokens', $usage) || \array_key_exists('cache_read_input_tokens', $usage)) {
            $cachedTokens = ($usage['cache_creation_input_tokens'] ?? 0) + ($usage['cache_read_input_tokens'] ?? 0);
        }

        return new TokenUsage(
            promptTokens: $usage['input_tokens'] ?? null,
            completionTokens: $usage['output_tokens'] ?? null,
            toolTokens: $usage['server_tool_use']['web_search_requests'] ?? null,
            cachedTokens: $cachedTokens,
        );
    }
}

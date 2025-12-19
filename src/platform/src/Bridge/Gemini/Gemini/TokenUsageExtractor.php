<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Gemini;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        if ($options['stream'] ?? false) {
            // Streams have to be handled manually as the tokens are part of the streamed chunks
            return null;
        }

        $content = $rawResult->getData();

        if (!\array_key_exists('usageMetadata', $content)) {
            return null;
        }

        return new TokenUsage(
            promptTokens: $content['usageMetadata']['promptTokenCount'] ?? null,
            completionTokens: $content['usageMetadata']['candidatesTokenCount'] ?? null,
            thinkingTokens: $content['usageMetadata']['thoughtsTokenCount'] ?? null,
            toolTokens: $content['usageMetadata']['toolUsePromptTokenCount'] ?? null,
            cachedTokens: $content['usageMetadata']['cachedContentTokenCount'] ?? null,
            totalTokens: $content['usageMetadata']['totalTokenCount'] ?? null,
        );
    }
}

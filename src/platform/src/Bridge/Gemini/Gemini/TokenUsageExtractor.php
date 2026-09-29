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

        return $this->fromUsageMetadata($content['usageMetadata'], $content['modelVersion'] ?? null);
    }

    /**
     * @param array{
     *     promptTokenCount?: int,
     *     candidatesTokenCount?: int,
     *     thoughtsTokenCount?: int,
     *     toolUsePromptTokenCount?: int,
     *     cachedContentTokenCount?: int,
     *     totalTokenCount?: int
     * } $usage
     */
    public function fromUsageMetadata(array $usage, ?string $model = null): TokenUsage
    {
        return new TokenUsage(
            promptTokens: $usage['promptTokenCount'] ?? null,
            completionTokens: $usage['candidatesTokenCount'] ?? null,
            thinkingTokens: $usage['thoughtsTokenCount'] ?? null,
            toolTokens: $usage['toolUsePromptTokenCount'] ?? null,
            cachedTokens: $usage['cachedContentTokenCount'] ?? null,
            totalTokens: $usage['totalTokenCount'] ?? null,
            model: $model,
        );
    }
}

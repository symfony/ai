<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Gemini;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        if ($options['stream'] ?? false) {
            $lastChunk = null;

            foreach ($rawResult->getDataStream() as $chunk) {
                // Store last event that contains usage metadata
                if (isset($chunk['usageMetadata'])) {
                    $lastChunk = $chunk;
                }
            }

            if ($lastChunk) {
                return $this->extractUsageMetadata($lastChunk['usageMetadata']);
            }

            return null;
        }

        $content = $rawResult->getData();

        if (!\array_key_exists('usageMetadata', $content)) {
            return null;
        }

        return $this->extractUsageMetadata($content['usageMetadata']);
    }

    /**
     * @param array{
     *     promptTokenCount?: int,
     *     candidatesTokenCount?: int,
     *     thoughtsTokenCount?: int,
     *     cachedContentTokenCount?: int,
     *     totalTokenCount?: int
     * } $usage
     */
    private function extractUsageMetadata(array $usage): TokenUsage
    {
        return new TokenUsage(
            promptTokens: $usage['promptTokenCount'] ?? null,
            completionTokens: $usage['candidatesTokenCount'] ?? null,
            thinkingTokens: $usage['thoughtsTokenCount'] ?? null,
            cachedTokens: $usage['cachedContentTokenCount'] ?? null,
            totalTokens: $usage['totalTokenCount'] ?? null,
        );
    }
}

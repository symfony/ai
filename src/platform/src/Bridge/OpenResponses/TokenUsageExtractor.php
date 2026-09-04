<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsage
    {
        if ($options['stream'] ?? false) {
            return null;
        }

        $content = $rawResult->getData();

        if (!\array_key_exists('usage', $content)) {
            return null;
        }

        return $this->fromDataArray($content, $this->extractRemainingTokens($rawResult));
    }

    /**
     * @param array{usage: array{
     *     input_tokens?: int,
     *     input_tokens_details?: array{
     *         cached_tokens?: int,
     *     },
     *     output_tokens?: int,
     *     output_tokens_details?: array{
     *         reasoning_tokens?: int,
     *     },
     *     total_tokens?: int,
     * }, model?: string} $data
     */
    public function fromDataArray(array $data, ?int $remainingTokens = null): TokenUsage
    {
        return new TokenUsage(
            promptTokens: $data['usage']['input_tokens'] ?? null,
            completionTokens: $data['usage']['output_tokens'] ?? null,
            thinkingTokens: $data['usage']['output_tokens_details']['reasoning_tokens'] ?? null,
            cachedTokens: $data['usage']['input_tokens_details']['cached_tokens'] ?? null,
            remainingTokens: $remainingTokens,
            totalTokens: $data['usage']['total_tokens'] ?? null,
            model: $data['model'] ?? null,
        );
    }

    /**
     * Resolves the remaining token quota from the raw response.
     *
     * The generic Responses API does not expose this; provider-specific bridges
     * can override this to read their own rate-limit headers.
     */
    protected function extractRemainingTokens(RawResultInterface $rawResult): ?int
    {
        return null;
    }
}

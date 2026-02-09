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
final class TokenUsageExtractor implements TokenUsageExtractorInterface
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

        return $this->fromDataArray($content);
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
     * }} $data
     */
    public function fromDataArray(array $data): TokenUsage
    {
        return new TokenUsage(
            promptTokens: $data['usage']['input_tokens'] ?? null,
            completionTokens: $data['usage']['output_tokens'] ?? null,
            thinkingTokens: $data['usage']['output_tokens_details']['reasoning_tokens'] ?? null,
            cachedTokens: $data['usage']['input_tokens_details']['cached_tokens'] ?? null,
            totalTokens: $data['usage']['total_tokens'] ?? null,
        );
    }
}

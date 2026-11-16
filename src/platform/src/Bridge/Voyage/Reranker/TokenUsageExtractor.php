<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Reranker;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Extracts token usage from the `usage.total_tokens` field returned by
 * Voyage's reranking API responses.
 *
 * @author Pauline Vos <pauline.vos@mongodb.com>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        $totalTokens = $rawResult->getData()['usage']['total_tokens'] ?? null;

        if (null === $totalTokens) {
            return null;
        }

        return new TokenUsage(
            totalTokens: $totalTokens,
        );
    }
}

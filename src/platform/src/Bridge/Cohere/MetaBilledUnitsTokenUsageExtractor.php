<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Extracts token usage from the `meta.billed_units` field returned by
 * Cohere's embeddings and reranking API responses.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MetaBilledUnitsTokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        $content = $rawResult->getData();

        $billedUnits = $content['meta']['billed_units'] ?? null;
        if (null === $billedUnits) {
            return null;
        }

        $inputTokens = $billedUnits['input_tokens'] ?? null;
        $searchUnits = $billedUnits['search_units'] ?? null;

        if (null === $inputTokens && null === $searchUnits) {
            return null;
        }

        return new TokenUsage(
            promptTokens: $inputTokens ?? $searchUnits,
        );
    }
}

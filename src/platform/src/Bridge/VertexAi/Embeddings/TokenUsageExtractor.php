<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Embeddings;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * @author Pascal Cescon <pascal.cescon@gmail.com>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        $content = $rawResult->getData();

        if (!\array_key_exists('predictions', $content)) {
            return null;
        }

        $totalTokens = 0;
        foreach ($content['predictions'] as $prediction) {
            if (isset($prediction['embeddings']['statistics']['token_count'])) {
                $totalTokens += (int) $prediction['embeddings']['statistics']['token_count'];
            }
        }

        if (0 === $totalTokens) {
            return null;
        }

        return new TokenUsage(
            promptTokens: $totalTokens,
            totalTokens: $totalTokens,
        );
    }
}

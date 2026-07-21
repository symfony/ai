<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        if ($options['stream'] ?? false) {
            return null;
        }

        $content = $rawResult->getData();
        $usage = $content['usage'] ?? null;

        if (!\is_array($usage)) {
            return null;
        }

        return new TokenUsage(
            promptTokens: $usage['inputTokens'] ?? null,
            completionTokens: $usage['outputTokens'] ?? null,
            thinkingTokens: $usage['thoughtTokens'] ?? null,
            totalTokens: $usage['totalTokens'] ?? null,
        );
    }
}

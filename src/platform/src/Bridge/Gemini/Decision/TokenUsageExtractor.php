<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Decision;

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
        $usage = $rawResult->getData()['usage'] ?? null;

        if (!\is_array($usage)) {
            return null;
        }

        return new TokenUsage(
            promptTokens: $usage['total_input_tokens'] ?? null,
            completionTokens: $usage['total_output_tokens'] ?? null,
            totalTokens: $usage['total_tokens'] ?? null,
        );
    }
}

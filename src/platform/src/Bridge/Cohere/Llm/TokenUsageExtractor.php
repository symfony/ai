<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Llm;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsage
    {
        if ($options['stream'] ?? false) {
            return null;
        }

        $content = $rawResult->getData();

        $tokens = $content['usage']['tokens'] ?? null;
        if (null === $tokens) {
            return null;
        }

        return new TokenUsage(
            promptTokens: $tokens['input_tokens'] ?? null,
            completionTokens: $tokens['output_tokens'] ?? null,
        );
    }
}

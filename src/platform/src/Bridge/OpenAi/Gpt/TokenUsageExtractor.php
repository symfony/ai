<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Gpt;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsage
    {
        if ($options['stream'] ?? false) {
            // Streams have to be handled manually as the tokens are part of the streamed chunks
            return null;
        }

        $rawResponse = $rawResult->getObject();
        if (!$rawResponse instanceof ResponseInterface) {
            return null;
        }

        $content = $rawResult->getData();

        if (!\array_key_exists('usage', $content)) {
            return null;
        }

        $remainingTokens = $rawResponse->getHeaders(false)['x-ratelimit-remaining-tokens'][0] ?? null;

        return new TokenUsage(
            promptTokens: $content['usage']['input_tokens'] ?? null,
            completionTokens: $content['usage']['output_tokens'] ?? null,
            thinkingTokens: $content['usage']['output_tokens_details']['reasoning_tokens'] ?? null,
            cachedTokens: $content['usage']['input_tokens_details']['cached_tokens'] ?? null,
            remainingTokens: null !== $remainingTokens ? (int) $remainingTokens : null,
            totalTokens: $content['usage']['total_tokens'] ?? null,
        );
    }
}

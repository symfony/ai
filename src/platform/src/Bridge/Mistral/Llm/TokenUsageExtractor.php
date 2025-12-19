<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Llm;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Quentin Fahrner <fahrner.quentin@gmail.com>
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

        $headers = $rawResponse->getHeaders(false);

        $remainingTokensMinute = $headers['x-ratelimit-limit-tokens-minute'][0] ?? null;
        $remainingTokensMonth = $headers['x-ratelimit-limit-tokens-month'][0] ?? null;

        $content = $rawResult->getData();

        return new TokenUsage(
            promptTokens: $content['usage']['prompt_tokens'] ?? null,
            completionTokens: $content['usage']['completion_tokens'] ?? null,
            remainingTokensMinute: null !== $remainingTokensMinute ? (int) $remainingTokensMinute : null,
            remainingTokensMonth: null !== $remainingTokensMonth ? (int) $remainingTokensMonth : null,
            totalTokens: $content['usage']['total_tokens'] ?? null,
        );
    }
}

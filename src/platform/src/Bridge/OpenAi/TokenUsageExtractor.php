<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TokenUsage\Attribute\AsTokenUsageExtractor;
use Symfony\AI\Platform\Result\TokenUsage\TokenUsage;
use Symfony\AI\Platform\Result\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
#[AsTokenUsageExtractor(platform: 'openai')]
class TokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function extractTokenUsage(Output $output): ?TokenUsage
    {
        if ($output->result instanceof StreamResult) {
            return null;
        }

        $rawResponse = $output->result->getRawResult()?->getObject();

        if (!$rawResponse instanceof ResponseInterface) {
            return null;
        }

        $remainingTokens = $rawResponse->getHeaders(false)['x-ratelimit-remaining-tokens'][0] ?? null;
        $tokenUsage = new TokenUsage(
            remaining: null !== $remainingTokens ? (int) $remainingTokens : null,
        );

        $data = $rawResponse->toArray(false);
        $usage = $data['usage'] ?? null;

        if (null === $usage) {
            return $tokenUsage;
        }

        $tokenUsage->prompt = $usage['prompt_tokens'] ?? null;
        $tokenUsage->completion = $usage['completion_tokens'] ?? null;
        $tokenUsage->total = $usage['total_tokens'] ?? null;

        return $tokenUsage;
    }
}

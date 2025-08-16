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
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Result\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class TokenOutputProcessor implements OutputProcessorInterface
{
    public function processOutput(Output $output): void
    {
        if ($output->result instanceof StreamResult) {
            // Streams have to be handled manually as the tokens are part of the streamed chunks
            return;
        }

        $rawResponse = $output->result->getRawResult()?->getObject();
        if (!$rawResponse instanceof ResponseInterface) {
            return;
        }

        $metadata = $output->result->getMetadata();

        $remainingTokens = $rawResponse->getHeaders(false)['x-ratelimit-remaining-tokens'][0] ?? null;
        $tokenUsage = new TokenUsage(
            remainingTokens: null !== $remainingTokens ? (int) $remainingTokens : null,
        );

        $content = $rawResponse->toArray(false);

        if (!\array_key_exists('usage', $content)) {
            $metadata->add('token_usage', $tokenUsage);

            return;
        }

        $usage = $content['usage'];

        $tokenUsage->promptTokens = $usage['prompt_tokens'] ?? null;
        $tokenUsage->completionTokens = $usage['completion_tokens'] ?? null;
        $tokenUsage->thinkingTokens = $usage['completion_tokens_details']['reasoning_tokens'] ?? null;
        $tokenUsage->cachedTokens = $usage['prompt_tokens_details']['cached_tokens'] ?? null;
        $tokenUsage->totalTokens = $usage['total_tokens'] ?? null;

        $metadata->add('token_usage', $tokenUsage);
    }
}

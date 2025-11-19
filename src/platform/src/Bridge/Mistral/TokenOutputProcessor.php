<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral;

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Quentin Fahrner <fahrner.quentin@gmail.com>
 */
final class TokenOutputProcessor implements OutputProcessorInterface
{
    public function processOutput(Output $output): void
    {
        if ($output->getResult() instanceof StreamResult) {
            // Streams have to be handled manually as the tokens are part of the streamed chunks
            return;
        }

        $rawResponse = $output->getResult()->getRawResult()?->getObject();
        if (!$rawResponse instanceof ResponseInterface) {
            return;
        }

        $metadata = $output->getResult()->getMetadata();
        $headers = $rawResponse->getHeaders(false);

        $remainingTokensMinute = $headers['x-ratelimit-limit-tokens-minute'][0] ?? null;
        $remainingTokensMonth = $headers['x-ratelimit-limit-tokens-month'][0] ?? null;
        $tokenUsage = new TokenUsage(
            remainingTokensMinute: null !== $remainingTokensMinute ? (int) $remainingTokensMinute : null,
            remainingTokensMonth: null !== $remainingTokensMonth ? (int) $remainingTokensMonth : null,
        );

        $content = $rawResponse->toArray(false);

        if (!\array_key_exists('usage', $content)) {
            $metadata->add('token_usage', $tokenUsage);

            return;
        }

        $usage = $content['usage'];

        $tokenUsage->promptTokens = $usage['prompt_tokens'] ?? null;
        $tokenUsage->completionTokens = $usage['completion_tokens'] ?? null;
        $tokenUsage->totalTokens = $usage['total_tokens'] ?? null;

        $metadata->add('token_usage', $tokenUsage);
    }
}

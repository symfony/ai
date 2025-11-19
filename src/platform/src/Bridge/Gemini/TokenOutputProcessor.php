<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

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

        $tokenUsage = new TokenUsage();

        $content = $rawResponse->toArray(false);
        if (!\array_key_exists('usageMetadata', $content)) {
            $metadata->add('token_usage', $tokenUsage);

            return;
        }

        $usage = $content['usageMetadata'];

        $tokenUsage->promptTokens = $usage['promptTokenCount'] ?? null;
        $tokenUsage->completionTokens = $usage['candidatesTokenCount'] ?? null;
        $tokenUsage->thinkingTokens = $usage['thoughtsTokenCount'] ?? null;
        $tokenUsage->toolTokens = $usage['toolUsePromptTokenCount'] ?? null;
        $tokenUsage->cachedTokens = $usage['cachedContentTokenCount'] ?? null;
        $tokenUsage->totalTokens = $usage['totalTokenCount'] ?? null;

        $metadata->add('token_usage', $tokenUsage);
    }
}

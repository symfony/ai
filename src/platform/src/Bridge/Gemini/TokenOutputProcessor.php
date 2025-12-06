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
        $content = $rawResponse->toArray(false);

        $tokenUsage = new TokenUsage(
            promptTokens: $content['usageMetadata']['promptTokenCount'] ?? null,
            completionTokens: $content['usageMetadata']['candidatesTokenCount'] ?? null,
            thinkingTokens: $content['usageMetadata']['thoughtsTokenCount'] ?? null,
            toolTokens: $content['usageMetadata']['toolUsePromptTokenCount'] ?? null,
            cachedTokens: $content['usageMetadata']['cachedContentTokenCount'] ?? null,
            totalTokens: $content['usageMetadata']['totalTokenCount'] ?? null,
        );

        $metadata->add('token_usage', $tokenUsage);
    }
}

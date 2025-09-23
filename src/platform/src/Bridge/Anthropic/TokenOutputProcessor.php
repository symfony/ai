<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

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

        $tokenUsage = new TokenUsage();

        $content = $rawResponse->toArray(false);
        if (!\array_key_exists('usage', $content)) {
            $metadata->add('token_usage', $tokenUsage);

            return;
        }

        $usage = $content['usage'];

        $tokenUsage->promptTokens = $usage['input_tokens'] ?? null;
        $tokenUsage->completionTokens = $usage['output_tokens'] ?? null;
        $tokenUsage->toolTokens = $usage['server_tool_use']['web_search_requests'] ?? null;

        $cachedTokens = null;
        if (\array_key_exists('cache_creation_input_tokens', $usage) || \array_key_exists('cache_read_input_tokens', $usage)) {
            $cachedTokens = ($usage['cache_creation_input_tokens'] ?? 0) + ($usage['cache_read_input_tokens'] ?? 0);
        }
        $tokenUsage->cachedTokens = $cachedTokens;

        $metadata->add('token_usage', $tokenUsage);
    }
}

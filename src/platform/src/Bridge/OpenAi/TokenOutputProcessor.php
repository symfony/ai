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

        $metadata->add(
            'remaining_tokens',
            (int) $rawResponse->getHeaders(false)['x-ratelimit-remaining-tokens'][0],
        );

        $content = $rawResponse->toArray(false);

        if (!\array_key_exists('usage', $content)) {
            return;
        }

        $metadata->add('prompt_tokens', $content['usage']['prompt_tokens'] ?? null);
        $metadata->add('completion_tokens', $content['usage']['completion_tokens'] ?? null);
        $metadata->add('total_tokens', $content['usage']['total_tokens'] ?? null);
    }
}

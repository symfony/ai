<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TokenOutputProcessor implements OutputProcessorInterface
{
    public function processOutput(Output $output): void
    {
        $result = $output->getResult();
        $currentOutputMetadata = $result->getMetadata();

        if ($result instanceof StreamResult) {
            foreach ($result->getContent() as $chunk) {
                if ($chunk instanceof OllamaMessageChunk && !$chunk->isDone()) {
                    continue;
                }

                $currentOutputMetadata->add('token_usage', new TokenUsage(
                    promptTokens: $chunk->raw['prompt_eval_count'],
                    completionTokens: $chunk->raw['eval_count']
                ));
            }

            return;
        }

        $rawResponse = $result->getRawResult()?->getObject();
        if (!$rawResponse instanceof ResponseInterface) {
            return;
        }

        $payload = $rawResponse->toArray();

        $currentOutputMetadata->add('token_usage', new TokenUsage(
            promptTokens: $payload['prompt_eval_count'],
            completionTokens: $payload['eval_count']
        ));
    }
}

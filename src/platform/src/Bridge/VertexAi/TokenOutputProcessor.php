<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Result\Metadata\Metadata;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class TokenOutputProcessor implements OutputProcessorInterface
{
    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function processOutput(Output $output): void
    {
        $metadata = $output->result->getMetadata();

        if ($output->result instanceof StreamResult) {
            $lastChunk = null;

            foreach ($output->result->getContent() as $chunk) {
                // Store last event that contains usage metadata
                if (isset($chunk['usageMetadata'])) {
                    $lastChunk = $chunk;
                }
            }

            if ($lastChunk) {
                $this->extractUsageMetadata($lastChunk['usageMetadata'], $metadata);
            }

            return;
        }

        $rawResponse = $output->result->getRawResult()?->getObject();
        if (!$rawResponse instanceof ResponseInterface) {
            return;
        }

        $content = $rawResponse->toArray(false);

        if (!isset($content['usageMetadata'])) {
            return;
        }

        $this->extractUsageMetadata($content['usageMetadata'], $metadata);
    }

    /**
     * @param array{
     *     promptTokenCount?: int,
     *     candidatesTokenCount?: int,
     *     thoughtsTokenCount?: int,
     *     totalTokenCount?: int
     * } $usage
     */
    private function extractUsageMetadata(array $usage, Metadata $metadata): void
    {
        $metadata->add('prompt_tokens', $usage['promptTokenCount'] ?? null);
        $metadata->add('completion_tokens', $usage['candidatesTokenCount'] ?? null);
        $metadata->add('thinking_tokens', $usage['thoughtsTokenCount'] ?? null);
        $metadata->add('total_tokens', $usage['totalTokenCount'] ?? null);
    }
}

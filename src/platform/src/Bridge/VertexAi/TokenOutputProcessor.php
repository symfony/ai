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
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Metadata\TokenUsage;
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
        $tokenUsage = new TokenUsage();
        $metadata = $output->getResult()->getMetadata();

        if ($output->getResult() instanceof StreamResult) {
            $lastChunk = null;

            foreach ($output->getResult()->getContent() as $chunk) {
                // Store last event that contains usage metadata
                if (isset($chunk['usageMetadata'])) {
                    $lastChunk = $chunk;
                }
            }

            if ($lastChunk) {
                $this->extractUsageMetadata($lastChunk['usageMetadata'], $metadata, $tokenUsage);
            }

            return;
        }

        $rawResponse = $output->getResult()->getRawResult()?->getObject();
        if (!$rawResponse instanceof ResponseInterface) {
            return;
        }

        $content = $rawResponse->toArray(false);

        if (!isset($content['usageMetadata'])) {
            $metadata->add('token_usage', $tokenUsage);

            return;
        }

        $this->extractUsageMetadata($content['usageMetadata'], $metadata, $tokenUsage);
    }

    /**
     * @param array{
     *     promptTokenCount?: int,
     *     candidatesTokenCount?: int,
     *     thoughtsTokenCount?: int,
     *     cachedContentTokenCount?: int,
     *     totalTokenCount?: int
     * } $usage
     */
    private function extractUsageMetadata(array $usage, Metadata $metadata, TokenUsage $tokenUsage): void
    {
        $tokenUsage->promptTokens = $usage['promptTokenCount'] ?? null;
        $tokenUsage->completionTokens = $usage['candidatesTokenCount'] ?? null;
        $tokenUsage->thinkingTokens = $usage['thoughtsTokenCount'] ?? null;
        $tokenUsage->cachedTokens = $usage['cachedContentTokenCount'] ?? null;
        $tokenUsage->totalTokens = $usage['totalTokenCount'] ?? null;

        $metadata->add('token_usage', $tokenUsage);
    }
}

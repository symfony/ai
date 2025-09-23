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

use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Claude;
    }

    public function convert(RawHttpResult|RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (429 === $response->getStatusCode()) {
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
            $retryAfterValue = $retryAfter ? (float) $retryAfter : null;
            throw new RateLimitExceededException($retryAfterValue);
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($response));
        }

        $data = $result->getData();

        if (!isset($data['content']) || [] === $data['content']) {
            throw new RuntimeException('Response does not contain any content.');
        }

        $toolCalls = [];
        foreach ($data['content'] as $content) {
            if ('tool_use' === $content['type']) {
                $toolCalls[] = new ToolCall($content['id'], $content['name'], $content['input']);
            }
        }

        if (!isset($data['content'][0]['text']) && [] === $toolCalls) {
            throw new RuntimeException('Response content does not contain any text nor tool calls.');
        }

        if ([] !== $toolCalls) {
            return new ToolCallResult(...$toolCalls);
        }

        return new TextResult($data['content'][0]['text']);
    }

    private function convertStream(HttpResponse $result): \Generator
    {
        foreach ((new EventSourceHttpClient())->stream($result) as $chunk) {
            if (!$chunk instanceof ServerSentEvent || '[DONE]' === $chunk->getData()) {
                continue;
            }

            $data = $chunk->getArrayData();

            if ('content_block_delta' != $data['type'] || !isset($data['delta']['text'])) {
                continue;
            }

            yield $data['delta']['text'];
        }
    }
}

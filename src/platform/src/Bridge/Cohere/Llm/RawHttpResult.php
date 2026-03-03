<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Llm;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Cohere v2 SSE uses `event:` + `data:` fields. To properly parse these events,
 * streaming must use the same EventSourceHttpClient that created the response.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RawHttpResult implements RawResultInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly EventSourceHttpClient $httpClient,
    ) {
    }

    public function getData(): array
    {
        return $this->response->toArray(false);
    }

    public function getDataStream(): iterable
    {
        foreach ($this->httpClient->stream($this->response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            if (!$chunk instanceof ServerSentEvent) {
                continue;
            }

            $jsonData = $chunk->getData();
            if ('' === $jsonData || '[DONE]' === $jsonData) {
                continue;
            }

            yield json_decode($jsonData, true, flags: \JSON_THROW_ON_ERROR);
        }
    }

    public function getObject(): ResponseInterface
    {
        return $this->response;
    }
}

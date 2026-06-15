<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream;

use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Handles Server-Sent Events streaming responses that advertise the
 * "text/event-stream" content type, so the HTTP client assembles each event
 * into a {@see ServerSentEvent} chunk before it reaches this decoder.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SseStream implements HttpStreamInterface
{
    public function stream(ResponseInterface $response): iterable
    {
        foreach ((new EventSourceHttpClient())->stream($response) as $chunk) {
            if (!$chunk instanceof ServerSentEvent) {
                continue;
            }

            $data = $chunk->getData();
            if ('' === $data || '[DONE]' === $data) {
                continue;
            }

            yield json_decode($data, true, flags: \JSON_THROW_ON_ERROR);
        }
    }
}

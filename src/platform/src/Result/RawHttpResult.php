<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RawHttpResult implements RawResultInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
    ) {
    }

    public function getData(): array
    {
        return $this->response->toArray(false);
    }

    public function getDataStream(): iterable
    {
        foreach ((new EventSourceHttpClient())->stream($this->response) as $chunk) {
            // Do not handle: First, Last, Error, Comments and API Specific "[DONE]"
            if (
                $chunk->isFirst()
                || $chunk->isLast()
                || null !== $chunk->getError()
                || str_starts_with($chunk->getContent(), ':')
                || ($chunk instanceof ServerSentEvent && '[DONE]' === $chunk->getData())
            ) {
                continue;
            }

            if ($chunk instanceof ServerSentEvent) {
                // handle complete SSE
                yield $chunk->getArrayData();
            } elseif ($chunk instanceof DataChunk) {
                // Handle single delta arrays
                $jsonDelta = $chunk->getContent();

                // Remove leading/trailing brackets
                if (str_starts_with($jsonDelta, '[') || str_starts_with($jsonDelta, ',')) {
                    $jsonDelta = substr($jsonDelta, 1);
                }
                if (str_ends_with($jsonDelta, ']')) {
                    $jsonDelta = substr($jsonDelta, 0, -1);
                }

                $deltas = explode(",\r\n", $jsonDelta);
                $deltas = array_map(function ($string) { return trim($string); }, $deltas);
                $deltas = array_filter($deltas, function ($item) { return '' !== $item; });
                foreach ($deltas as $delta) {
                    yield json_decode($delta, true, flags: \JSON_THROW_ON_ERROR);
                }
            }
        }
    }

    public function getObject(): ResponseInterface
    {
        return $this->response;
    }
}

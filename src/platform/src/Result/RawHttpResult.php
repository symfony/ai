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
            if ($chunk->isFirst() || $chunk->isLast() || ($chunk instanceof ServerSentEvent && '[DONE]' === $chunk->getData())) {
                continue;
            }

            $jsonDelta = $chunk instanceof ServerSentEvent ? $chunk->getData() : $chunk->getContent();

            // Remove leading/trailing brackets
            if (str_starts_with($jsonDelta, '[') || str_starts_with($jsonDelta, ',')) {
                $jsonDelta = substr($jsonDelta, 1);
            }
            if (str_ends_with($jsonDelta, ']')) {
                $jsonDelta = substr($jsonDelta, 0, -1);
            }

            // Split in case of multiple JSON objects
            $deltas = explode(",\r\n", $jsonDelta);

            foreach ($deltas as $delta) {
                if ('' === trim($delta)) {
                    continue;
                }

                yield json_decode($delta, true, flags: \JSON_THROW_ON_ERROR);
            }
        }
    }

    public function getObject(): ResponseInterface
    {
        return $this->response;
    }
}

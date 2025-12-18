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
            // Handle only complete events (no need for handle DataChunk`s)
            if (!$chunk instanceof ServerSentEvent) {
                continue;
            }

            // Do not handle: Init, Terminate, Errors, Comments and openAI specific termination via DONE
            if (
                $chunk->isFirst()
                || $chunk->isLast()
                || null !== $chunk->getError()
                || str_starts_with($chunk->getContent(), ':')
                || '[DONE]' === $chunk->getData()
            ) {
                continue;
            }

            yield $chunk->getArrayData();
        }
    }

    public function getObject(): ResponseInterface
    {
        return $this->response;
    }
}

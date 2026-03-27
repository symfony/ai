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

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Handles NDJSON (Newline Delimited JSON) streaming responses.
 *
 * Unlike SSE-based streaming (handled by RawHttpResult), NDJSON sends one
 * complete JSON object per line, separated by newlines. This is the format
 * used by Ollama's /api/chat endpoint when streaming.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class NdjsonHttpResult implements RawResultInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getData(): array
    {
        return $this->response->toArray(false);
    }

    public function getDataStream(): iterable
    {
        $buffer = '';

        foreach ($this->httpClient->stream($this->response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            $buffer .= $chunk->getContent();

            while (false !== ($pos = strpos($buffer, "\n"))) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);

                if ('' === $line) {
                    continue;
                }

                yield json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
            }
        }

        $buffer = trim($buffer);

        if ('' !== $buffer) {
            yield json_decode($buffer, true, flags: \JSON_THROW_ON_ERROR);
        }
    }

    public function getObject(): ResponseInterface
    {
        return $this->response;
    }
}

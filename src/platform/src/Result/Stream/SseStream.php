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

use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Handles SSE (Server-Sent Events) streaming responses.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SseStream implements HttpStreamInterface
{
    public function stream(ResponseInterface $response): iterable
    {
        $buffer = '';

        foreach ((new EventSourceHttpClient())->stream($response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            $buffer .= $chunk->getContent();

            while (false !== ($pos = strpos($buffer, "\n\n"))) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $data = null;
                foreach (explode("\n", $event) as $line) {
                    // Comments start with ":"
                    if ('' === $line || str_starts_with($line, ':')) {
                        continue;
                    }

                    if (str_starts_with($line, 'data: ')) {
                        $data = substr($line, 6);
                    }
                }

                if (null === $data || '[DONE]' === $data) {
                    continue;
                }

                // Remove leading/trailing brackets
                if (str_starts_with($data, '[') || str_starts_with($data, ',')) {
                    $data = substr($data, 1);
                }
                if (str_ends_with($data, ']')) {
                    $data = substr($data, 0, -1);
                }

                // Split in case of multiple JSON objects
                $deltas = explode(",\r\n", $data);

                foreach ($deltas as $delta) {
                    if ('' === trim($delta)) {
                        continue;
                    }

                    yield json_decode($delta, true, flags: \JSON_THROW_ON_ERROR);
                }
            }
        }
    }
}

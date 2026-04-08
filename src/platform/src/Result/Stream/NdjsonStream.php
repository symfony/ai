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
 * Handles NDJSON (Newline Delimited JSON) streaming responses.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class NdjsonStream implements HttpStreamInterface
{
    public function stream(ResponseInterface $response): iterable
    {
        $buffer = '';

        foreach ((new EventSourceHttpClient())->stream($response) as $chunk) {
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
}

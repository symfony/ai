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
 * Handles Server-Sent Events streaming responses that omit the
 * "text/event-stream" content type (e.g. the ChatGPT Codex inference
 * endpoint). Without that header the HTTP client never assembles the
 * "event:"/"data:" framing into {@see ServerSentEvent} chunks, so the raw body
 * is buffered here and split on blank-line event separators.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RawSseStream implements HttpStreamInterface
{
    public function stream(ResponseInterface $response): iterable
    {
        $buffer = '';

        foreach ((new EventSourceHttpClient())->stream($response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            // Defensive: the content type may have been present after all, in
            // which case the client already framed the event for us.
            if ($chunk instanceof ServerSentEvent) {
                if (null !== ($decoded = $this->decode($chunk->getData()))) {
                    yield $decoded;
                }

                continue;
            }

            $buffer .= $chunk->getContent();

            while (1 === preg_match('/(?:\r\n){2,}|\r{2,}|\n{2,}/', $buffer, $match, \PREG_OFFSET_CAPTURE)) {
                $event = substr($buffer, 0, $match[0][1]);
                $buffer = substr($buffer, $match[0][1] + \strlen($match[0][0]));

                if (null !== ($decoded = $this->decode($this->extractEventData($event)))) {
                    yield $decoded;
                }
            }
        }

        // Flush a trailing event that ended without a final blank-line separator.
        if ('' !== trim($buffer) && null !== ($decoded = $this->decode($this->extractEventData($buffer)))) {
            yield $decoded;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(?string $data): ?array
    {
        if (null === $data) {
            return null;
        }

        $data = trim($data);
        if ('' === $data || '[DONE]' === $data) {
            return null;
        }

        return json_decode($data, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Extracts the concatenated "data:" payload from a raw SSE event block.
     */
    private function extractEventData(string $event): ?string
    {
        if (str_starts_with($event, "\xEF\xBB\xBF")) {
            $event = substr($event, 3);
        }

        $data = null;
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $event)) as $line) {
            // lines starting with a colon identify as comment
            if (str_starts_with($line, ':')) {
                continue;
            }

            if (str_starts_with($line, 'data:')) {
                $payload = ltrim(substr($line, 5), ' ');
                $data = null === $data ? $payload : $data."\n".$payload;
            }
        }

        return $data;
    }
}

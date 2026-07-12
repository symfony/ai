<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp;

use Symfony\AI\Platform\Bridge\Acp\Exception\ProtocolException;
use Symfony\AI\Platform\Bridge\Acp\Exception\TransportException;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * Wraps ACP process output as a raw result.
 */
final class RawProcessResult implements RawResultInterface
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $data = null;

    /**
     * @var list<array<string, mixed>>
     */
    private array $lines = [];

    private bool $drained = false;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $response = null;

    public function __construct(
        private readonly ModelClient $client,
        private readonly int $requestId,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if (!$this->drained) {
            $this->drain();
        }

        return $this->data ?? [];
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function getDataStream(): \Generator
    {
        if ($this->drained) {
            foreach ($this->lines as $line) {
                yield $line;
            }

            return;
        }

        yield from $this->streamLines();
    }

    /**
     * Reads pending notifications after response.
     *
     * @return list<array<string, mixed>>
     */
    public function drainPending(): array
    {
        $late = [];

        while (true) {
            try {
                $message = $this->client->readNextMessage();

                if (isset($message['id'])) {
                    if ($message['id'] === $this->requestId) {
                        $this->response = $message;
                        $this->processResponse();

                        break;
                    }
                } else {
                    $this->lines[] = $message;
                    $late[] = $message;
                }
            } catch (TransportException) {
                break;
            }
        }

        return $late;
    }

    public function getObject(): object
    {
        if (!$this->drained) {
            $this->drain();
        }

        if (null === $this->response) {
            return new \stdClass();
        }

        $json = json_encode($this->response, \JSON_THROW_ON_ERROR);

        return json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponse(): ?array
    {
        if (!$this->drained) {
            $this->drain();
        }

        return $this->response;
    }

    private function drain(): void
    {
        foreach ($this->streamLines() as $_) {
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function streamLines(): \Generator
    {
        while (true) {
            $message = $this->client->readNextMessage();

            if (isset($message['id'])) {
                if ($message['id'] === $this->requestId) {
                    $this->response = $message;
                    $this->processResponse();
                    $this->drained = true;

                    break;
                }

                throw new ProtocolException(\sprintf('Unexpected response ID "%s", expected "%s".', $message['id'], $this->requestId));
            }

            $this->lines[] = $message;
            yield $message;
        }
    }

    private function processResponse(): void
    {
        if (null === $this->response) {
            return;
        }

        if (isset($this->response['error'])) {
            $error = $this->response['error'];
            $message = \is_array($error) ? (string) ($error['message'] ?? 'Unknown ACP error') : 'Unknown ACP error';
            throw new ProtocolException($message);
        }

        $this->data = \is_array($this->response['result'] ?? null) ? $this->response['result'] : [];
    }
}

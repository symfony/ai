<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server\Transport\StreamableHttp;

use Symfony\AI\McpSdk\Server\Transport\Sse\StoreInterface;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\Session;
use Symfony\AI\McpSdk\Server\TransportInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final readonly class StreamTransport implements TransportInterface
{
    private Uuid $streamUuid;
    public function __construct(
        private StoreInterface $store,
        //private Request $request,
        private int $streamId,
        private Session $session,
        private array $initialEvents = [],
    ) {
        $this->streamUuid = $this->session->getStreamUuid($this->streamId);
    }

    public function initialize(): void
    {
        ignore_user_abort(true);
        foreach ($this->initialEvents as $id => $data) { //@todo id not passed here
            $this->flushEvent($id, 'message', $data);
        }
    }

    public function isConnected(): bool
    {
        return 0 === connection_aborted();
    }

    public function receive(): \Generator
    {
        yield $this->store->pop($this->streamUuid);
    }

    public function send(string $data): void
    {
        $id = Uuid::v4()->toString();
        $this->session->addEventOnStream($this->streamId, $id, $data);
        $this->flushEvent($id, 'message', $data);
    }

    public function close(): void
    {
        $this->store->remove($this->streamUuid);
    }

    private function flushEvent(string $id, string $event, string $data): void
    {
        echo \sprintf('event: %s', $event).\PHP_EOL;
        echo \sprintf('id: %s', $id).\PHP_EOL;
        echo \sprintf('data: %s', $data).\PHP_EOL;
        echo \PHP_EOL;
        if (false !== ob_get_length()) {
            ob_flush();
        }
        flush();
    }
}

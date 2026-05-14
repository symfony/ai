<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests\Fake;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\Http\Client\Response;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Websocket\Client\WebsocketConnection;
use Amp\Websocket\WebsocketCloseCode;
use Amp\Websocket\WebsocketCloseInfo;
use Amp\Websocket\WebsocketCount;
use Amp\Websocket\WebsocketMessage;
use Amp\Websocket\WebsocketTimestamp;

/**
 * In-memory replacement for {@see WebsocketConnection} that records every frame the bridge sends
 * and yields a pre-defined queue of inbound messages.
 *
 * Only the methods exercised by the production code are functional; everything else returns benign
 * defaults so PHPStan can still type-check the interface.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @implements \IteratorAggregate<int, WebsocketMessage>
 */
final class FakeWebsocketConnection implements WebsocketConnection, \IteratorAggregate
{
    /**
     * @var list<string>
     */
    public array $sentText = [];

    /**
     * @var list<string>
     */
    public array $sentBinary = [];

    private bool $closed = false;
    private ?WebsocketCloseInfo $closeInfo = null;

    /**
     * @param list<WebsocketMessage> $messages
     */
    public function __construct(
        private array $messages = [],
        private readonly bool $throwOnSend = false,
    ) {
    }

    public function getIterator(): \Traversable
    {
        while ([] !== $this->messages) {
            yield array_shift($this->messages);
        }

        $this->markClosed(WebsocketCloseCode::NORMAL_CLOSE, '', byPeer: true);
    }

    public function receive(?Cancellation $cancellation = null): ?WebsocketMessage
    {
        if ([] === $this->messages) {
            $this->markClosed(WebsocketCloseCode::NORMAL_CLOSE, '', byPeer: true);

            return null;
        }

        return array_shift($this->messages);
    }

    public function getId(): int
    {
        return 1;
    }

    public function getLocalAddress(): SocketAddress
    {
        throw new \BadMethodCallException(__METHOD__.' is not implemented in tests.');
    }

    public function getRemoteAddress(): SocketAddress
    {
        throw new \BadMethodCallException(__METHOD__.' is not implemented in tests.');
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return null;
    }

    public function getCloseInfo(): WebsocketCloseInfo
    {
        return $this->closeInfo ?? new WebsocketCloseInfo(WebsocketCloseCode::NORMAL_CLOSE, '', 0.0, false);
    }

    public function isCompressionEnabled(): bool
    {
        return false;
    }

    public function sendText(string $data): void
    {
        if ($this->throwOnSend) {
            throw new \RuntimeException('Simulated send failure.');
        }

        $this->sentText[] = $data;
    }

    public function sendBinary(string $data): void
    {
        if ($this->throwOnSend) {
            throw new \RuntimeException('Simulated send failure.');
        }

        $this->sentBinary[] = $data;
    }

    public function streamText(ReadableStream $stream): void
    {
        throw new \BadMethodCallException(__METHOD__.' is not implemented in tests.');
    }

    public function streamBinary(ReadableStream $stream): void
    {
        throw new \BadMethodCallException(__METHOD__.' is not implemented in tests.');
    }

    public function ping(): void
    {
    }

    public function getCount(WebsocketCount $type): int
    {
        return 0;
    }

    public function getTimestamp(WebsocketTimestamp $type): float
    {
        return 0.0;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function close(int $code = WebsocketCloseCode::NORMAL_CLOSE, string $reason = ''): void
    {
        $this->markClosed($code, $reason, byPeer: false);
    }

    public function onClose(\Closure $onClose): void
    {
    }

    public function getHandshakeResponse(): Response
    {
        throw new \BadMethodCallException(__METHOD__.' is not implemented in tests.');
    }

    private function markClosed(int $code, string $reason, bool $byPeer): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->closeInfo = new WebsocketCloseInfo($code, $reason, microtime(true), $byPeer);
    }
}

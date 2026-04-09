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

use Amp\Websocket\Client\WebsocketConnection;
use Amp\Websocket\Client\WebsocketHandshake;
use Symfony\AI\Platform\Bridge\Deepgram\Websocket\WebsocketConnectorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FakeWebsocketConnector implements WebsocketConnectorInterface
{
    public ?WebsocketHandshake $lastHandshake = null;

    public function __construct(
        private readonly ?FakeWebsocketConnection $connection = null,
    ) {
    }

    public function connect(WebsocketHandshake $handshake): WebsocketConnection
    {
        $this->lastHandshake = $handshake;

        return $this->connection ?? new FakeWebsocketConnection();
    }
}

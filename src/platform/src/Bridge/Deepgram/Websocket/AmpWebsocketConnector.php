<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Websocket;

use Amp\Websocket\Client\WebsocketConnection;
use Amp\Websocket\Client\WebsocketHandshake;

use function Amp\Websocket\Client\connect;

/**
 * Default WebsocketConnectorInterface implementation backed by amphp/websocket-client.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class AmpWebsocketConnector implements WebsocketConnectorInterface
{
    public function connect(WebsocketHandshake $handshake): WebsocketConnection
    {
        return connect($handshake);
    }
}

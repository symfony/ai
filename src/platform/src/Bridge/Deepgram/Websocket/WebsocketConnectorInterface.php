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

/**
 * Decouples WebsocketClient from the underlying transport so it can be unit-tested
 * with a fake implementation that records the frames it receives.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface WebsocketConnectorInterface
{
    public function connect(WebsocketHandshake $handshake): WebsocketConnection;
}

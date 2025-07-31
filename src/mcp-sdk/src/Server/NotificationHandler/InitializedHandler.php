<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server\NotificationHandler;

use Symfony\AI\McpSdk\Message\Notification;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\Session;

final class InitializedHandler extends BaseNotificationHandler
{
    public function __construct(private readonly ?Session $session = null) { }

    protected function supportedNotification(): string
    {
        return 'initialized';
    }

    public function handle(Notification $notification): void
    {
        $this->session?->setClientNotificationInitializedReceived();
    }
}

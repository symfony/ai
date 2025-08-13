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

final class InitializedHandler extends BaseNotificationHandler
{
    public function handle(Notification $notification): void
    {
    }

    protected function supportedNotification(): string
    {
        return 'initialized';
    }
}

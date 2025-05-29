<?php

declare(strict_types=1);

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
use Symfony\AI\McpSdk\Server\NotificationHandlerInterface;

abstract class BaseNotificationHandler implements NotificationHandlerInterface
{
    public function supports(Notification $message): bool
    {
        return $message->method === \sprintf('notifications/%s', $this->supportedNotification());
    }

    abstract protected function supportedNotification(): string;
}

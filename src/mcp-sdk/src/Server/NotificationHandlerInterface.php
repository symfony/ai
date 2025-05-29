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

namespace Symfony\AI\McpSdk\Server;

use Symfony\AI\McpSdk\Message\Notification;

interface NotificationHandlerInterface
{
    public function supports(Notification $message): bool;

    public function handle(Notification $notification): null;
}

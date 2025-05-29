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

namespace Symfony\AI\McpSdk\Message;

final class Factory
{
    public function create(string $json): Request|Notification
    {
        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        if (!isset($data['method'])) {
            throw new \InvalidArgumentException('Invalid JSON-RPC request, missing method');
        }

        if (str_starts_with((string) $data['method'], 'notifications/')) {
            return Notification::from($data);
        }

        return Request::from($data);
    }
}

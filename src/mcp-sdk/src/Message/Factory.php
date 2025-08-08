<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Message;

use Symfony\AI\McpSdk\Exception\InvalidInputMessageException;

final class Factory
{
    /**
     * @param string $input
     * @return Notification|Request|InvalidInputMessageException
     *
     * @throws \JsonException When the input string is not valid JSON
     */
    public function create(string $input): Notification|Request|InvalidInputMessageException
    {
        $message = json_decode($input, true, flags: \JSON_THROW_ON_ERROR);
        if (!isset($message['method'])) {
            return new InvalidInputMessageException('Invalid JSON-RPC request, missing "method".');
        } elseif (str_starts_with((string) $message['method'], 'notifications/')) {
            return Notification::from($message);
        } else {
            return Request::from($message);
        }
    }
}

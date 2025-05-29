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

namespace Symfony\AI\McpSdk\Tests\Fixtures;

use Symfony\AI\McpSdk\Server\TransportInterface;

class InMemoryTransport implements TransportInterface
{
    private bool $connected = true;

    /**
     * @param list<string> $messages
     */
    public function __construct(
        private readonly array $messages = [],
    ) {
    }

    public function initialize(): void
    {
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function receive(): \Generator
    {
        yield from $this->messages;
        $this->connected = false;
    }

    public function send(string $data): void
    {
    }

    public function close(): void
    {
    }
}

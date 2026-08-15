<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Client;

use Symfony\AI\McpBundle\Exception\InvalidArgumentException;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpClient implements McpClientInterface
{
    /**
     * @param ServiceProviderInterface<ServerConnectionInterface> $connections keyed by server name
     */
    public function __construct(
        private readonly string $name,
        private readonly ServiceProviderInterface $connections,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function has(string $server): bool
    {
        return $this->connections->has($server);
    }

    public function get(string $server): ServerConnectionInterface
    {
        if (!$this->connections->has($server)) {
            throw new InvalidArgumentException(\sprintf('The MCP client "%s" has no server named "%s". Configured: "%s".', $this->name, $server, implode(', ', $this->getServerNames())));
        }

        return $this->connections->get($server);
    }

    public function getServerNames(): array
    {
        return array_keys($this->connections->getProvidedServices());
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->getServerNames() as $server) {
            yield $server => $this->connections->get($server);
        }
    }

    public function count(): int
    {
        return \count($this->getServerNames());
    }

    public function disconnect(): void
    {
        foreach ($this as $connection) {
            $connection->disconnect();
        }
    }
}

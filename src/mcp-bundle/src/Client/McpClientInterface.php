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

/**
 * A configured MCP client and the set of remote servers it connects to.
 *
 * Connections are resolved lazily, so iterating a client does not open any of them.
 *
 * @extends \IteratorAggregate<string, ServerConnectionInterface>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface McpClientInterface extends \IteratorAggregate, \Countable
{
    /**
     * The configured name of this client (mcp.clients.<name>).
     */
    public function getName(): string;

    public function has(string $server): bool;

    /**
     * @throws InvalidArgumentException when this client has no such server configured
     */
    public function get(string $server): ServerConnectionInterface;

    /**
     * @return list<string>
     */
    public function getServerNames(): array;

    /**
     * Closes every connection this client has opened.
     */
    public function disconnect(): void;
}

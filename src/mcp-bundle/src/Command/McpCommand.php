<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Command;

use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Runs one configured MCP server over STDIO.
 *
 * The transport owns the process' STDIN and STDOUT, so exactly one server can be served per
 * invocation — hence a server argument rather than one command per server.
 */
#[AsCommand('mcp:server', 'Starts an MCP server over STDIO')]
final class McpCommand
{
    /**
     * @param ServiceProviderInterface<Server> $servers the servers with the STDIO transport enabled
     */
    public function __construct(
        private readonly ServiceProviderInterface $servers,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Name of the server to run (mcp.servers.<name>)', suggestedValues: [self::class, 'suggestServers'])]
        ?string $name = null,
    ): int {
        $names = $this->getServerNames();

        if (null === $name) {
            if (1 !== \count($names)) {
                $io->error(\sprintf('Several MCP servers expose the STDIO transport, name the one to run: %s.', implode(', ', $names)));

                return Command::INVALID;
            }

            $name = $names[0];
        }

        if (!$this->servers->has($name)) {
            $io->error(\sprintf('No MCP server named "%s" exposes the STDIO transport.%s', $name, [] === $names ? '' : \sprintf(' Available: %s.', implode(', ', $names))));

            return Command::INVALID;
        }

        $this->servers->get($name)->run(new StdioTransport(logger: $this->logger));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    public function suggestServers(): array
    {
        return $this->getServerNames();
    }

    /**
     * @return list<string>
     */
    private function getServerNames(): array
    {
        return array_keys($this->servers->getProvidedServices());
    }
}

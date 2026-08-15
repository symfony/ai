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

use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\AI\McpBundle\Client\ServerConnectionInterface;
use Symfony\AI\McpBundle\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Inspects a configured MCP client and prints what its remote servers advertise.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('mcp:client:debug', 'Inspect a configured MCP client and what its remote servers advertise')]
final class ClientDebugCommand
{
    /**
     * @param ServiceProviderInterface<McpClientInterface> $clients
     */
    public function __construct(
        private readonly ServiceProviderInterface $clients,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Name of a configured client (mcp.clients.<name>)', suggestedValues: [self::class, 'suggestClients'])]
        ?string $client = null,
        #[Argument(description: 'Name of one of its servers (mcp.clients.<client>.servers.<name>)', suggestedValues: [self::class, 'suggestServers'])]
        ?string $server = null,
    ): int {
        $names = $this->getClientNames();

        // Without a client, stay a pure configuration view: connecting to every server of every
        // client would spawn processes and open sessions nobody asked for.
        if (null === $client) {
            return $this->listClients($io, $names);
        }

        if (!$this->clients->has($client)) {
            $io->error(\sprintf('No MCP client named "%s" is configured.%s', $client, [] === $names ? '' : \sprintf(' Available: %s.', implode(', ', $names))));

            return Command::INVALID;
        }

        $mcpClient = $this->clients->get($client);
        $servers = $mcpClient->getServerNames();

        if (null === $server) {
            if (1 !== \count($servers)) {
                $io->error(\sprintf('The MCP client "%s" connects to several servers, name the one to inspect: %s.', $client, implode(', ', $servers)));

                return Command::INVALID;
            }

            $server = $servers[0];
        }

        if (!$mcpClient->has($server)) {
            $io->error(\sprintf('The MCP client "%s" has no server named "%s". Configured: %s.', $client, $server, implode(', ', $servers)));

            return Command::INVALID;
        }

        return $this->describe($io, $mcpClient->get($server));
    }

    /**
     * @return list<string>
     */
    public function suggestClients(): array
    {
        return $this->getClientNames();
    }

    /**
     * @return list<string>
     */
    public function suggestServers(CompletionInput $input): array
    {
        $client = $input->getArgument('client');

        if (!\is_string($client) || !$this->clients->has($client)) {
            return [];
        }

        return $this->clients->get($client)->getServerNames();
    }

    /**
     * @param list<string> $names
     */
    private function listClients(SymfonyStyle $io, array $names): int
    {
        if ([] === $names) {
            $io->warning('No MCP client is configured. Declare one under "mcp.clients" in config/packages/mcp.yaml.');

            return Command::SUCCESS;
        }

        $io->title('Configured MCP clients');
        $io->table(['Client', 'Servers'], array_map(
            fn (string $name): array => [$name, implode(', ', $this->clients->get($name)->getServerNames())],
            $names,
        ));
        $io->text('Run "mcp:client:debug <client> <server>" to connect and list what a server advertises.');

        return Command::SUCCESS;
    }

    private function describe(SymfonyStyle $io, ServerConnectionInterface $connection): int
    {
        $io->title(\sprintf('MCP client "%s" → server "%s"', $connection->getClientName(), $connection->getName()));

        try {
            // The connection opens on this first call; no connect() to forget.
            $info = $connection->getServerInfo();

            if (null !== $info) {
                $io->definitionList(
                    ['Name' => $info->name],
                    ['Version' => $info->version],
                );
            }

            if (null !== ($instructions = $connection->getInstructions())) {
                $io->section('Instructions');
                $io->writeln($instructions);
            }

            $this->renderTools($io, $connection->getTools());
            $this->renderPrompts($io, $connection->getPrompts());
            $this->renderResources($io, $connection->getResources(), $connection->getResourceTemplates());
        } catch (ExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } finally {
            // Always terminates a stdio child process, including on failure.
            $connection->disconnect();
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<Tool> $tools
     */
    private function renderTools(SymfonyStyle $io, array $tools): void
    {
        $io->section(\sprintf('Tools (%d)', \count($tools)));

        if ([] === $tools) {
            $io->writeln('<comment>No tools advertised by the server.</comment>');

            return;
        }

        $io->table(['Name', 'Description', 'Required arguments'], array_map(static fn (Tool $tool): array => [
            $tool->name,
            $tool->description ?? '',
            implode(', ', $tool->inputSchema['required'] ?? []),
        ], $tools));
    }

    /**
     * @param list<Prompt> $prompts
     */
    private function renderPrompts(SymfonyStyle $io, array $prompts): void
    {
        if ([] === $prompts) {
            return;
        }

        $io->section(\sprintf('Prompts (%d)', \count($prompts)));
        $io->table(['Name', 'Description', 'Arguments'], array_map(static fn (Prompt $prompt): array => [
            $prompt->name,
            $prompt->description ?? '',
            implode(', ', array_map(
                static fn ($argument): string => $argument->name.($argument->required ? '' : '?'),
                $prompt->arguments ?? [],
            )),
        ], $prompts));
    }

    /**
     * @param list<ResourceDefinition> $resources
     * @param list<ResourceTemplate>   $templates
     */
    private function renderResources(SymfonyStyle $io, array $resources, array $templates): void
    {
        if ([] !== $resources) {
            $io->section(\sprintf('Resources (%d)', \count($resources)));
            $io->table(['URI', 'Name', 'MIME Type'], array_map(static fn (ResourceDefinition $resource): array => [
                $resource->uri,
                $resource->name,
                $resource->mimeType ?? '',
            ], $resources));
        }

        if ([] !== $templates) {
            $io->section(\sprintf('Resource Templates (%d)', \count($templates)));
            $io->table(['URI Template', 'Name', 'MIME Type'], array_map(static fn (ResourceTemplate $template): array => [
                $template->uriTemplate,
                $template->name,
                $template->mimeType ?? '',
            ], $templates));
        }
    }

    /**
     * @return list<string>
     */
    private function getClientNames(): array
    {
        return array_keys($this->clients->getProvidedServices());
    }
}

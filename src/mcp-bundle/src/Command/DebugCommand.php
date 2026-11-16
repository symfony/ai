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
use Mcp\Server\Builder;
use Mcp\Server\Capability\RegistryInterface;
use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\AI\McpBundle\Client\ServerConnectionInterface;
use Symfony\AI\McpBundle\Exception\ExceptionInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Shows both sides of the bundle: what the configured servers expose, and what the configured
 * clients reach.
 *
 * Useful to verify that an attributed class was actually picked up: elements are registered from
 * container services and have to be listed by a server, so a class that is neither a registered
 * (autoconfigured) service nor matched by a capability list will not show up.
 *
 * Only the client modes open a connection; everything else reads the compiled container.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('debug:mcp', 'Display the configured MCP servers and clients')]
final class DebugCommand
{
    /**
     * @param ServiceProviderInterface<Builder>            $builders
     * @param ServiceProviderInterface<RegistryInterface>  $registries
     * @param ServiceProviderInterface<McpClientInterface> $clients
     * @param array<string, list<string>>                  $unassigned kind => service ids no server exposes
     */
    public function __construct(
        private readonly ServiceProviderInterface $builders,
        private readonly ServiceProviderInterface $registries,
        private readonly ServiceProviderInterface $clients,
        private readonly array $unassigned = [],
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'A tool/prompt name, resource URI, or resource template to show details for')]
        ?string $name = null,
        #[Option(description: 'Restrict the output to one server. With --client, the remote server to connect to.', suggestedValues: [self::class, 'suggestServers'])]
        ?string $server = null,
        #[Option(description: 'Connect a configured client (mcp.clients.<name>) and list what its server advertises', suggestedValues: [self::class, 'suggestClients'])]
        ?string $client = null,
        #[Option(description: 'List the configured clients and their servers without connecting to any')]
        bool $clients = false,
    ): int {
        if (null !== $client) {
            return $this->describeConnection($io, $client, $server);
        }

        if ($clients) {
            return $this->listClients($io);
        }

        $names = $this->getServerNames();

        if (null !== $server) {
            if (!$this->registries->has($server)) {
                $io->error(\sprintf('No MCP server named "%s" is configured.%s', $server, [] === $names ? '' : \sprintf(' Available: %s.', implode(', ', $names))));

                return Command::INVALID;
            }

            $names = [$server];
        }

        if ([] === $names) {
            $io->warning('No MCP server is configured. Declare one under "mcp.servers" in config/packages/mcp.yaml.');

            return Command::SUCCESS;
        }

        $found = false;
        foreach ($names as $current) {
            // The registry is populated by the loaders when the server is built.
            $this->builders->get($current)->build();
            $registry = $this->registries->get($current);

            if (null !== $name) {
                $found = $this->describeElement($io, $registry, $current, $name) || $found;

                continue;
            }

            if (1 < \count($names) || null !== $server) {
                $io->title(\sprintf('Server "%s"', $current));
            }

            $this->listElements($io, $registry);
        }

        if (null !== $name && !$found) {
            $io->error(\sprintf('No MCP capability named "%s" is registered. Run "debug:mcp" without arguments to list all.', $name));

            return Command::FAILURE;
        }

        if (null === $name) {
            $this->reportUnassigned($io);
            $this->summarizeClients($io);
        }

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
    public function suggestClients(): array
    {
        return $this->getClientNames();
    }

    /**
     * @return list<string>
     */
    private function getServerNames(): array
    {
        return array_keys($this->registries->getProvidedServices());
    }

    /**
     * @return list<string>
     */
    private function getClientNames(): array
    {
        return array_keys($this->clients->getProvidedServices());
    }

    /**
     * Container introspection only: answering "which clients do I have" must not spawn a stdio
     * child process or open an HTTP session.
     */
    private function listClients(SymfonyStyle $io): int
    {
        $names = $this->getClientNames();

        if ([] === $names) {
            $io->warning('No MCP client is configured. Declare one under "mcp.clients" in config/packages/mcp.yaml.');

            return Command::SUCCESS;
        }

        $io->title('Configured MCP clients');
        $io->table(['Client', 'Servers'], array_map(
            fn (string $name): array => [$name, implode(', ', $this->clients->get($name)->getServerNames())],
            $names,
        ));
        $io->text('Run "debug:mcp --client=<client> --server=<server>" to connect and list what a server advertises.');

        return Command::SUCCESS;
    }

    /**
     * Mentioned after the server listing so one command still shows both sides of the bundle.
     */
    private function summarizeClients(SymfonyStyle $io): void
    {
        $names = $this->getClientNames();
        if ([] === $names) {
            return;
        }

        $io->section(\sprintf('Clients (%d)', \count($names)));
        $io->table(['Client', 'Servers'], array_map(
            fn (string $name): array => [$name, implode(', ', $this->clients->get($name)->getServerNames())],
            $names,
        ));
        $io->text('Run "debug:mcp --client=<client>" to connect and list what a server advertises.');
    }

    private function describeConnection(SymfonyStyle $io, string $client, ?string $server): int
    {
        $names = $this->getClientNames();

        if (!$this->clients->has($client)) {
            $io->error(\sprintf('No MCP client named "%s" is configured.%s', $client, [] === $names ? '' : \sprintf(' Available: %s.', implode(', ', $names))));

            return Command::INVALID;
        }

        $mcpClient = $this->clients->get($client);
        $servers = $mcpClient->getServerNames();

        if (null === $server) {
            if (1 !== \count($servers)) {
                $io->error(\sprintf('The MCP client "%s" connects to several servers, name the one to inspect with --server: %s.', $client, implode(', ', $servers)));

                return Command::INVALID;
            }

            $server = $servers[0];
        }

        if (!$mcpClient->has($server)) {
            $io->error(\sprintf('The MCP client "%s" has no server named "%s". Configured: %s.', $client, $server, implode(', ', $servers)));

            return Command::INVALID;
        }

        return $this->describeRemote($io, $mcpClient->get($server));
    }

    private function describeRemote(SymfonyStyle $io, ServerConnectionInterface $connection): int
    {
        $io->title(\sprintf('MCP client "%s" → server "%s"', $connection->getClientName(), $connection->getName()));

        try {
            // The connection opens on this first call; there is no connect() to forget.
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

            $this->renderRemoteTools($io, $connection->getTools());
            $this->renderRemotePrompts($io, $connection->getPrompts());
            $this->renderRemoteResources($io, $connection->getResources(), $connection->getResourceTemplates());
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
    private function renderRemoteTools(SymfonyStyle $io, array $tools): void
    {
        $io->section(\sprintf('Tools (%d)', \count($tools)));

        if ([] === $tools) {
            $io->writeln('<comment>No tools advertised by the server.</comment>');

            return;
        }

        $io->table(['Name', 'Description', 'Required arguments'], array_map(fn (Tool $tool): array => [
            $tool->name,
            $this->truncate($tool->description),
            implode(', ', $tool->inputSchema['required'] ?? []),
        ], $tools));
    }

    /**
     * @param list<Prompt> $prompts
     */
    private function renderRemotePrompts(SymfonyStyle $io, array $prompts): void
    {
        if ([] === $prompts) {
            return;
        }

        $io->section(\sprintf('Prompts (%d)', \count($prompts)));
        $io->table(['Name', 'Description', 'Arguments'], array_map(fn (Prompt $prompt): array => [
            $prompt->name,
            $this->truncate($prompt->description),
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
    private function renderRemoteResources(SymfonyStyle $io, array $resources, array $templates): void
    {
        if ([] !== $resources) {
            $io->section(\sprintf('Resources (%d)', \count($resources)));
            $io->table(['URI', 'Name', 'MIME Type'], array_map(static fn (ResourceDefinition $resource): array => [
                $resource->uri,
                $resource->name,
                $resource->mimeType ?? '',
            ], $resources));
        }

        if ([] === $templates) {
            return;
        }

        $io->section(\sprintf('Resource Templates (%d)', \count($templates)));
        $io->table(['URI Template', 'Name', 'MIME Type'], array_map(static fn (ResourceTemplate $template): array => [
            $template->uriTemplate,
            $template->name,
            $template->mimeType ?? '',
        ], $templates));
    }

    private function reportUnassigned(SymfonyStyle $io): void
    {
        $ids = array_merge(...array_values($this->unassigned));
        if ([] === $ids) {
            return;
        }

        $io->section('Not exposed by any server');
        $io->text('These services carry an MCP attribute but no server lists them. Add them to a server\'s element list to expose them.');
        $io->listing(array_values(array_unique($ids)));
    }

    private function listElements(SymfonyStyle $io, RegistryInterface $registry): void
    {
        $tools = iterator_to_array($registry->getTools());
        $prompts = iterator_to_array($registry->getPrompts());
        $resources = iterator_to_array($registry->getResources());
        $resourceTemplates = iterator_to_array($registry->getResourceTemplates());

        if ([] === $tools && [] === $prompts && [] === $resources && [] === $resourceTemplates) {
            $io->warning('No MCP capabilities are registered.');
            $io->text([
                'Capabilities are registered from container services carrying one of the MCP attributes',
                '(#[McpTool], #[McpPrompt], #[McpResource], #[McpResourceTemplate]) and listed by a server',
                'under "mcp.servers.<name>.tools" and friends. Make sure the classes are registered as',
                'services with autoconfiguration enabled and matched by one of those lists.',
            ]);

            return;
        }

        if ([] !== $tools) {
            $io->section(\sprintf('Tools (%d)', \count($tools)));
            $io->table(['Name', 'Handler', 'Description'], array_map(fn (Tool $tool): array => [
                $tool->name,
                $this->formatHandler($registry->getTool($tool->name)->handler),
                $this->truncate($tool->description),
            ], $tools));
        }

        if ([] !== $prompts) {
            $io->section(\sprintf('Prompts (%d)', \count($prompts)));
            $io->table(['Name', 'Handler', 'Description'], array_map(fn (Prompt $prompt): array => [
                $prompt->name,
                $this->formatHandler($registry->getPrompt($prompt->name)->handler),
                $this->truncate($prompt->description),
            ], $prompts));
        }

        if ([] !== $resources) {
            $io->section(\sprintf('Resources (%d)', \count($resources)));
            $io->table(['URI', 'Name', 'Handler', 'MIME Type'], array_map(fn (ResourceDefinition $resource): array => [
                $resource->uri,
                $resource->name,
                $this->formatHandler($registry->getResource($resource->uri, false)->handler),
                $resource->mimeType ?? '',
            ], $resources));
        }

        if ([] !== $resourceTemplates) {
            $io->section(\sprintf('Resource Templates (%d)', \count($resourceTemplates)));
            $io->table(['URI Template', 'Name', 'Handler', 'MIME Type'], array_map(fn (ResourceTemplate $template): array => [
                $template->uriTemplate,
                $template->name,
                $this->formatHandler($registry->getResourceTemplate($template->uriTemplate)->handler),
                $template->mimeType ?? '',
            ], $resourceTemplates));
        }
    }

    private function describeElement(SymfonyStyle $io, RegistryInterface $registry, string $server, string $name): bool
    {
        foreach ($registry->getTools() as $tool) {
            if ($tool->name === $name) {
                $io->title(\sprintf('Tool "%s" on server "%s"', $name, $server));
                $io->definitionList(
                    ['Name' => $tool->name],
                    ['Title' => $tool->title ?? '-'],
                    ['Description' => $tool->description ?? '-'],
                    ['Handler' => $this->formatHandler($registry->getTool($name)->handler)],
                );
                $io->section('Input Schema');
                $io->writeln(json_encode($tool->inputSchema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
                if (null !== $tool->outputSchema) {
                    $io->section('Output Schema');
                    $io->writeln(json_encode($tool->outputSchema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
                }

                return true;
            }
        }

        foreach ($registry->getPrompts() as $prompt) {
            if ($prompt->name === $name) {
                $io->title(\sprintf('Prompt "%s" on server "%s"', $name, $server));
                $io->definitionList(
                    ['Name' => $prompt->name],
                    ['Title' => $prompt->title ?? '-'],
                    ['Description' => $prompt->description ?? '-'],
                    ['Handler' => $this->formatHandler($registry->getPrompt($name)->handler)],
                    ['Arguments' => implode(', ', array_map(
                        static fn ($argument): string => $argument->name.($argument->required ? '' : '?'),
                        $prompt->arguments ?? [],
                    )) ?: '-'],
                );

                return true;
            }
        }

        foreach ($registry->getResources() as $resource) {
            if ($resource->uri === $name || $resource->name === $name) {
                $io->title(\sprintf('Resource "%s" on server "%s"', $resource->uri, $server));
                $io->definitionList(
                    ['URI' => $resource->uri],
                    ['Name' => $resource->name],
                    ['Description' => $resource->description ?? '-'],
                    ['MIME Type' => $resource->mimeType ?? '-'],
                    ['Handler' => $this->formatHandler($registry->getResource($resource->uri, false)->handler)],
                );

                return true;
            }
        }

        foreach ($registry->getResourceTemplates() as $template) {
            if ($template->uriTemplate === $name || $template->name === $name) {
                $io->title(\sprintf('Resource Template "%s" on server "%s"', $template->uriTemplate, $server));
                $io->definitionList(
                    ['URI Template' => $template->uriTemplate],
                    ['Name' => $template->name],
                    ['Description' => $template->description ?? '-'],
                    ['MIME Type' => $template->mimeType ?? '-'],
                    ['Handler' => $this->formatHandler($registry->getResourceTemplate($template->uriTemplate)->handler)],
                );

                return true;
            }
        }

        return false;
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function formatHandler(\Closure|array|string $handler): string
    {
        if ($handler instanceof \Closure) {
            return 'Closure';
        }

        if (\is_array($handler)) {
            return \sprintf('%s::%s()', \is_object($handler[0]) ? $handler[0]::class : $handler[0], $handler[1]);
        }

        return class_exists($handler) ? $handler.'::__invoke()' : $handler.'()';
    }

    private function truncate(?string $text, int $length = 80): string
    {
        if (null === $text) {
            return '';
        }

        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1).'…' : $text;
    }
}

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

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Server\Builder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists the MCP capabilities (tools, prompts, resources, resource templates) registered with the server.
 *
 * Useful to verify that an attributed class was actually picked up: elements are registered from
 * container services, so a class that is not a registered (autoconfigured) service will not show up.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand('debug:mcp', 'Display the MCP capabilities registered with the server')]
final class DebugCommand
{
    public function __construct(
        private readonly Builder $builder,
        private readonly RegistryInterface $registry,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'A tool/prompt name, resource URI, or resource template to show details for')]
        ?string $name = null,
    ): int {
        // The registry is populated by the loaders when the server is built.
        $this->builder->build();

        if (null !== $name) {
            return $this->describeElement($io, $name);
        }

        $tools = iterator_to_array($this->registry->getTools());
        $prompts = iterator_to_array($this->registry->getPrompts());
        $resources = iterator_to_array($this->registry->getResources());
        $resourceTemplates = iterator_to_array($this->registry->getResourceTemplates());

        if ([] === $tools && [] === $prompts && [] === $resources && [] === $resourceTemplates) {
            $io->warning('No MCP capabilities are registered.');
            $io->text([
                'Capabilities are registered from container services carrying one of the MCP attributes',
                '(#[McpTool], #[McpPrompt], #[McpResource], #[McpResourceTemplate]).',
                'Make sure the classes are registered as services with autoconfiguration enabled',
                'and not excluded from service registration in config/services.yaml.',
            ]);

            return Command::SUCCESS;
        }

        if ([] !== $tools) {
            $io->section(\sprintf('Tools (%d)', \count($tools)));
            $io->table(['Name', 'Handler', 'Description'], array_map(fn (Tool $tool): array => [
                $tool->name,
                $this->formatHandler($this->registry->getTool($tool->name)->handler),
                $this->truncate($tool->description),
            ], $tools));
        }

        if ([] !== $prompts) {
            $io->section(\sprintf('Prompts (%d)', \count($prompts)));
            $io->table(['Name', 'Handler', 'Description'], array_map(fn (Prompt $prompt): array => [
                $prompt->name,
                $this->formatHandler($this->registry->getPrompt($prompt->name)->handler),
                $this->truncate($prompt->description),
            ], $prompts));
        }

        if ([] !== $resources) {
            $io->section(\sprintf('Resources (%d)', \count($resources)));
            $io->table(['URI', 'Name', 'Handler', 'MIME Type'], array_map(fn (ResourceDefinition $resource): array => [
                $resource->uri,
                $resource->name,
                $this->formatHandler($this->registry->getResource($resource->uri, false)->handler),
                $resource->mimeType ?? '',
            ], $resources));
        }

        if ([] !== $resourceTemplates) {
            $io->section(\sprintf('Resource Templates (%d)', \count($resourceTemplates)));
            $io->table(['URI Template', 'Name', 'Handler', 'MIME Type'], array_map(fn (ResourceTemplate $template): array => [
                $template->uriTemplate,
                $template->name,
                $this->formatHandler($this->registry->getResourceTemplate($template->uriTemplate)->handler),
                $template->mimeType ?? '',
            ], $resourceTemplates));
        }

        return Command::SUCCESS;
    }

    private function describeElement(SymfonyStyle $io, string $name): int
    {
        foreach ($this->registry->getTools() as $tool) {
            if ($tool->name === $name) {
                $io->title(\sprintf('Tool "%s"', $name));
                $io->definitionList(
                    ['Name' => $tool->name],
                    ['Title' => $tool->title ?? '-'],
                    ['Description' => $tool->description ?? '-'],
                    ['Handler' => $this->formatHandler($this->registry->getTool($name)->handler)],
                );
                $io->section('Input Schema');
                $io->writeln(json_encode($tool->inputSchema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
                if (null !== $tool->outputSchema) {
                    $io->section('Output Schema');
                    $io->writeln(json_encode($tool->outputSchema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
                }

                return Command::SUCCESS;
            }
        }

        foreach ($this->registry->getPrompts() as $prompt) {
            if ($prompt->name === $name) {
                $io->title(\sprintf('Prompt "%s"', $name));
                $io->definitionList(
                    ['Name' => $prompt->name],
                    ['Title' => $prompt->title ?? '-'],
                    ['Description' => $prompt->description ?? '-'],
                    ['Handler' => $this->formatHandler($this->registry->getPrompt($name)->handler)],
                    ['Arguments' => implode(', ', array_map(
                        static fn ($argument): string => $argument->name.($argument->required ? '' : '?'),
                        $prompt->arguments ?? [],
                    )) ?: '-'],
                );

                return Command::SUCCESS;
            }
        }

        foreach ($this->registry->getResources() as $resource) {
            if ($resource->uri === $name || $resource->name === $name) {
                $io->title(\sprintf('Resource "%s"', $resource->uri));
                $io->definitionList(
                    ['URI' => $resource->uri],
                    ['Name' => $resource->name],
                    ['Description' => $resource->description ?? '-'],
                    ['MIME Type' => $resource->mimeType ?? '-'],
                    ['Handler' => $this->formatHandler($this->registry->getResource($resource->uri, false)->handler)],
                );

                return Command::SUCCESS;
            }
        }

        foreach ($this->registry->getResourceTemplates() as $template) {
            if ($template->uriTemplate === $name || $template->name === $name) {
                $io->title(\sprintf('Resource Template "%s"', $template->uriTemplate));
                $io->definitionList(
                    ['URI Template' => $template->uriTemplate],
                    ['Name' => $template->name],
                    ['Description' => $template->description ?? '-'],
                    ['MIME Type' => $template->mimeType ?? '-'],
                    ['Handler' => $this->formatHandler($this->registry->getResourceTemplate($template->uriTemplate)->handler)],
                );

                return Command::SUCCESS;
            }
        }

        $io->error(\sprintf('No MCP capability named "%s" is registered. Run "debug:mcp" without arguments to list all.', $name));

        return Command::FAILURE;
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

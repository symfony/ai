<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command;

use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display all MCP capabilities grouped by extension.
 *
 * @phpstan-import-type Capabilities from CapabilityCollector
 * @phpstan-import-type ExtensionData from DebugExtensionsCommand
 *
 * @phpstan-type CapabilitiesByExtension array<string, Capabilities>
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('debug:capabilities', 'Display all MCP capabilities grouped by extension')]
class DebugCapabilitiesCommand extends Command
{
    private CapabilityCollector $collector;

    /**
     * @var array<string, ExtensionData>
     */
    private array $extensions;

    public function __construct(
        LoggerInterface $logger,
        private ContainerInterface $container,
    ) {
        parent::__construct(self::getDefaultName());

        $rootDir = $container->getParameter('mate.root_dir');
        \assert(\is_string($rootDir));

        $extensions = $this->container->getParameter('mate.extensions') ?? [];
        \assert(\is_array($extensions));
        $this->extensions = $extensions;

        $disabledFeatures = $this->container->getParameter('mate.disabled_features') ?? [];
        \assert(\is_array($disabledFeatures));

        $this->collector = new CapabilityCollector($rootDir, $extensions, $disabledFeatures, $logger);
    }

    public static function getDefaultName(): string
    {
        return 'debug:capabilities';
    }

    public static function getDefaultDescription(): string
    {
        return 'Display all MCP capabilities grouped by extension';
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (text, json)', 'text')
            ->addOption('extension', null, InputOption::VALUE_REQUIRED, 'Filter by extension package name')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filter by type (tool, resource, prompt, template)')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command displays all discovered MCP capabilities
grouped by their providing extension/package.

<info>Usage Examples:</info>

  <comment># Show all capabilities</comment>
  %command.full_name%

  <comment># Show only tools</comment>
  %command.full_name% --type=tool

  <comment># Show capabilities from specific extension</comment>
  %command.full_name% --extension=symfony/ai-monolog-mate-extension

  <comment># Show only tools from specific extension</comment>
  %command.full_name% --extension=symfony/ai-monolog-mate-extension --type=tool

  <comment># JSON output for scripting</comment>
  %command.full_name% --format=json

  <comment># Root project capabilities</comment>
  %command.full_name% --extension=_custom
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $capabilities = [];
        foreach ($this->extensions as $extensionName => $extension) {
            $capabilities[$extensionName] = $this->collector->collectCapabilities($extensionName, $extension);
        }

        $extensionFilter = $input->getOption('extension');
        $typeFilter = $input->getOption('type');

        if (null !== $extensionFilter) {
            \assert(\is_string($extensionFilter));
            $capabilities = $this->filterExtensions($capabilities, $extensionFilter);
        }

        if (null !== $typeFilter) {
            \assert(\is_string($typeFilter));
            $capabilities = $this->filterByType($capabilities, $typeFilter);
        }

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if ('json' === $format) {
            $this->outputJson($capabilities, $output);
        } else {
            $this->outputText($capabilities, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * @param CapabilitiesByExtension $all
     *
     * @return CapabilitiesByExtension
     */
    private function filterExtensions(array $all, string $filter): array
    {
        if (!isset($all[$filter])) {
            throw InvalidArgumentException::forExtensionNotFound($filter, array_keys($all));
        }

        return [$filter => $all[$filter]];
    }

    /**
     * @param CapabilitiesByExtension $capabilities
     *
     * @return array<string, array<string, array<string, array<string, mixed>>>>
     */
    private function filterByType(array $capabilities, string $type): array
    {
        $validTypes = ['tool', 'resource', 'prompt', 'template'];
        if (!\in_array($type, $validTypes, true)) {
            throw InvalidArgumentException::forInvalidType($type, $validTypes);
        }

        $typeMap = [
            'tool' => 'tools',
            'resource' => 'resources',
            'prompt' => 'prompts',
            'template' => 'resource_templates',
        ];

        $filtered = [];
        foreach ($capabilities as $extension => $caps) {
            $filtered[$extension] = [
                $typeMap[$type] => $caps[$typeMap[$type]],
            ];
        }

        return $filtered;
    }

    /**
     * @param CapabilitiesByExtension $capabilitiesByExtension
     */
    private function outputText(array $capabilitiesByExtension, SymfonyStyle $io): void
    {
        $io->title('Mate MCP Capabilities');

        $totalTools = 0;
        $totalResources = 0;
        $totalPrompts = 0;
        $totalTemplates = 0;

        foreach ($capabilitiesByExtension as $extensionName => $capabilities) {
            $displayName = '_custom' === $extensionName
                ? 'Root Project'
                : $extensionName;

            $io->section($displayName);

            $tools = $capabilities['tools'] ?? [];
            if (\count($tools) > 0) {
                $io->text(\sprintf('<info>Tools (%d)</info>', \count($tools)));
                foreach ($tools as $name => $tool) {
                    $io->text(\sprintf('  • %s', $name));
                    $io->text(\sprintf('    Handler: %s', $tool['handler']));
                    if ('' !== ($tool['description'] ?? '')) {
                        $io->text(\sprintf('    Description: %s', $tool['description']));
                    }
                }
                $io->newLine();
                $totalTools += \count($tools);
            }

            $resources = $capabilities['resources'] ?? [];
            if (\count($resources) > 0) {
                $io->text(\sprintf('<info>Resources (%d)</info>', \count($resources)));
                foreach ($resources as $uri => $resource) {
                    $io->text(\sprintf('  • %s', $uri));
                    $io->text(\sprintf('    Handler: %s', $resource['handler']));
                    if ('' !== ($resource['description'] ?? '')) {
                        $io->text(\sprintf('    Description: %s', $resource['description']));
                    }
                    if ('' !== ($resource['mime_type'] ?? '')) {
                        $io->text(\sprintf('    MIME Type: %s', $resource['mime_type']));
                    }
                }
                $io->newLine();
                $totalResources += \count($resources);
            }

            $prompts = $capabilities['prompts'] ?? [];
            if (\count($prompts) > 0) {
                $io->text(\sprintf('<info>Prompts (%d)</info>', \count($prompts)));
                foreach ($prompts as $name => $prompt) {
                    $io->text(\sprintf('  • %s', $name));
                    $io->text(\sprintf('    Handler: %s', $prompt['handler']));
                    if ('' !== ($prompt['description'] ?? '')) {
                        $io->text(\sprintf('    Description: %s', $prompt['description']));
                    }
                }
                $io->newLine();
                $totalPrompts += \count($prompts);
            }

            $templates = $capabilities['resource_templates'] ?? [];
            if (\count($templates) > 0) {
                $io->text(\sprintf('<info>Resource Templates (%d)</info>', \count($templates)));
                foreach ($templates as $uriTemplate => $template) {
                    $io->text(\sprintf('  • %s', $uriTemplate));
                    $io->text(\sprintf('    Handler: %s', $template['handler']));
                    if ('' !== ($template['description'] ?? '')) {
                        $io->text(\sprintf('    Description: %s', $template['description']));
                    }
                }
                $io->newLine();
                $totalTemplates += \count($templates);
            }
        }

        $io->section('Summary');
        $io->text(\sprintf('Extensions: %d', \count($capabilitiesByExtension)));
        $io->text(\sprintf('Tools: %d', $totalTools));
        $io->text(\sprintf('Resources: %d', $totalResources));
        $io->text(\sprintf('Prompts: %d', $totalPrompts));
        $io->text(\sprintf('Templates: %d', $totalTemplates));
    }

    /**
     * @param CapabilitiesByExtension $capabilitiesByExtension
     */
    private function outputJson(array $capabilitiesByExtension, OutputInterface $output): void
    {
        $totalTools = 0;
        $totalResources = 0;
        $totalPrompts = 0;
        $totalTemplates = 0;

        foreach ($capabilitiesByExtension as $caps) {
            $totalTools += \count($caps['tools'] ?? []);
            $totalResources += \count($caps['resources'] ?? []);
            $totalPrompts += \count($caps['prompts'] ?? []);
            $totalTemplates += \count($caps['resource_templates'] ?? []);
        }

        $result = [
            'extensions' => $capabilitiesByExtension,
            'summary' => [
                'extensions' => \count($capabilitiesByExtension),
                'tools' => $totalTools,
                'resources' => $totalResources,
                'prompts' => $totalPrompts,
                'resource_templates' => $totalTemplates,
            ],
        ];

        $output->writeln(json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }
}

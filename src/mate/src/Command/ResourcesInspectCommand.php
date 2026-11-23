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

use HelgeSverre\Toon\Toon;
use Symfony\AI\Mate\Command\Trait\CollectsResourcesTrait;
use Symfony\AI\Mate\Command\Trait\EnsuresToonFormatAvailabilityTrait;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Display detailed information about a specific resource or resource template.
 *
 * @phpstan-import-type ResourceData from CollectsResourcesTrait
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('resources:inspect', 'Display detailed information about a specific resource or resource template')]
class ResourcesInspectCommand extends Command
{
    use CollectsResourcesTrait;
    use EnsuresToonFormatAvailabilityTrait;

    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     */
    public function __construct(
        private array $extensions,
        private CapabilityCollector $collector,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'resources:inspect';
    }

    public static function getDefaultDescription(): string
    {
        return 'Display detailed information about a specific resource or resource template';
    }

    protected function configure(): void
    {
        $script = $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate';

        $this
            ->addArgument('resource', InputArgument::REQUIRED, 'Name, URI or URI template of the resource to inspect')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (text, json, toon)', 'text')
            ->setHelp(
                <<<HELP
The <info>%command.name%</info> command displays a resource or resource template with its URI, parameters and MIME type.

The argument is a name, a URI template or a concrete URI. A concrete URI finds the template it matches.
When the resource declares an output schema, it is shown too. For a template with one schema per variant (the profiler collectors), a concrete URI narrows it to the matching variant, or to the variant titled "other" when none matches.

<info>Usage Examples:</info>

  <comment># By name</comment>
  %command.full_name% symfony-profile-data

  <comment># By a concrete URI</comment>
  %command.full_name% symfony-profiler://profile/abc123

  <comment># JSON output for scripting</comment>
  %command.full_name% symfony-profile-data --format=json

  <comment># For a list of all resources, use:</comment>
  {$script} resources:list
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $identifier = $input->getArgument('resource');
        \assert(\is_string($identifier));

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if (!$this->ensureFormatSupported($io, $format, ['text', 'json', 'toon'])) {
            return Command::FAILURE;
        }

        $resource = $this->find($this->collectResources($this->extensions, $this->collector), $identifier);

        if (null === $resource) {
            $io->error(\sprintf('Resource "%s" not found', $identifier));
            $io->note(\sprintf('Use "%s resources:list" to see all available resources', $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate'));

            return Command::FAILURE;
        }

        $resource['output_schema'] = $this->narrowSchema($resource, $identifier);

        if ('json' === $format) {
            $output->writeln(json_encode($resource, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ('toon' === $format) {
            $output->writeln(Toon::encode($resource));

            return Command::SUCCESS;
        }

        $this->outputText($resource, $io);

        return Command::SUCCESS;
    }

    /**
     * Prefers an exact URI or template, then a name, then a template a concrete URI matches.
     *
     * @param array<string, ResourceData> $resources
     *
     * @return ResourceData|null
     */
    private function find(array $resources, string $identifier): ?array
    {
        if (isset($resources[$identifier])) {
            return $resources[$identifier];
        }

        foreach ($resources as $resource) {
            if ($resource['name'] === $identifier) {
                return $resource;
            }
        }

        foreach ($resources as $resource) {
            if ('template' === $resource['type'] && 1 === preg_match($this->templateRegex($resource['uri']), $identifier)) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * A template whose schema is a `oneOf` of titled branches (the profiler collectors, for
     * one) shows only the branch whose title equals a variable of the concrete URI.
     *
     * @param ResourceData $resource
     *
     * @return array<string, mixed>|null
     */
    private function narrowSchema(array $resource, string $identifier): ?array
    {
        $schema = $resource['output_schema'];
        if (null === $schema || 'template' !== $resource['type'] || !isset($schema['oneOf']) || !\is_array($schema['oneOf'])) {
            return $schema;
        }

        if (1 !== preg_match($this->templateRegex($resource['uri']), $identifier) || [] === $resource['parameters']) {
            return $schema;
        }

        $values = $this->templateValues($resource['uri'], $identifier);
        foreach ($schema['oneOf'] as $branch) {
            if (\is_array($branch) && isset($branch['title']) && \in_array($branch['title'], $values, true)) {
                return $branch;
            }
        }

        foreach ($schema['oneOf'] as $branch) {
            if (\is_array($branch) && 'other' === ($branch['title'] ?? null)) {
                return $branch;
            }
        }

        return $schema;
    }

    /**
     * @return list<string>
     */
    private function templateValues(string $uriTemplate, string $uri): array
    {
        preg_match($this->templateRegex($uriTemplate), $uri, $matches);

        return \array_slice($matches, 1);
    }

    private function templateRegex(string $uriTemplate): string
    {
        $quoted = preg_quote($uriTemplate, '#');
        $pattern = preg_replace('/\\\\\{[^}]+\\\\\}/', '([^/]+)', $quoted);
        \assert(\is_string($pattern));

        return '#^'.$pattern.'$#';
    }

    /**
     * @param ResourceData $resource
     */
    private function outputText(array $resource, SymfonyStyle $io): void
    {
        $io->title($resource['name'] ?? $resource['uri']);

        $io->definitionList(
            ['Type' => $resource['type']],
            ['URI' => $resource['uri']],
            ['Description' => $resource['description'] ?? '<comment>N/A</comment>'],
            ['MIME Type' => $resource['mime_type'] ?? '<comment>N/A</comment>'],
            ['Handler' => $resource['handler']],
            ['Extension' => $resource['extension']],
        );

        if ([] !== $resource['parameters']) {
            $io->section('Parameters');
            $io->listing($resource['parameters']);
        }

        $io->section('Output Schema');
        $io->text(null !== $resource['output_schema'] ? json_encode($resource['output_schema'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) : '<comment>No output schema defined</comment>');

        $io->text(\sprintf('Read it with: <info>%s resources:read %s</info>', $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate', $resource['uri']));
    }
}

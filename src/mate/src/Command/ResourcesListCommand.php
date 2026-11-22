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
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Display available resources and resource templates with metadata.
 *
 * @phpstan-import-type ResourceData from CollectsResourcesTrait
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('resources:list', 'Display available resources and resource templates with metadata')]
class ResourcesListCommand extends Command
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
        return 'resources:list';
    }

    public static function getDefaultDescription(): string
    {
        return 'Display available resources and resource templates with metadata';
    }

    protected function configure(): void
    {
        $script = $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate';

        $this
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter by name or URI pattern (supports wildcards)')
            ->addOption('extension', null, InputOption::VALUE_REQUIRED, 'Filter by extension package name')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json, toon)', 'table')
            ->setHelp(
                <<<HELP
The <info>%command.name%</info> command displays all resources and resource templates with their metadata.

A template is a resource whose URI contains <comment>{placeholders}</comment>. Fill them in to read one.

<info>Usage Examples:</info>

  <comment># Show everything</comment>
  %command.full_name%

  <comment># Filter by name or URI pattern</comment>
  %command.full_name% --filter="symfony-profiler*"
  %command.full_name% --filter="symfony-profiler://*"

  <comment># Show resources from a specific extension</comment>
  %command.full_name% --extension=symfony/ai-symfony-mate-extension

  <comment># JSON output for scripting</comment>
  %command.full_name% --format=json

  <comment># For a single resource with its parameters, use:</comment>
  {$script} resources:inspect <name-or-uri>

  <comment># To read a resource, use:</comment>
  {$script} resources:read <uri>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if (!$this->ensureFormatSupported($io, $format, ['table', 'json', 'toon'])) {
            return Command::FAILURE;
        }

        $resources = $this->collectResources($this->extensions, $this->collector);

        $extensionFilter = $input->getOption('extension');
        if (null !== $extensionFilter) {
            \assert(\is_string($extensionFilter));
            $resources = $this->filterByExtension($resources, $extensionFilter);
        }

        $nameFilter = $input->getOption('filter');
        if (null !== $nameFilter) {
            \assert(\is_string($nameFilter));
            $resources = $this->filterByPattern($resources, $nameFilter);
        }

        if ('json' === $format) {
            $output->writeln(json_encode($this->getArrayResult($resources), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ('toon' === $format) {
            $output->writeln(Toon::encode($this->getArrayResult($resources)));

            return Command::SUCCESS;
        }

        $this->outputTable($resources, $io);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, ResourceData> $resources
     *
     * @return array<string, ResourceData>
     */
    private function filterByExtension(array $resources, string $extensionFilter): array
    {
        $filtered = array_filter($resources, static fn (array $resource): bool => $resource['extension'] === $extensionFilter);

        if ([] === $filtered) {
            $availableExtensions = array_unique(array_column($resources, 'extension'));
            throw new InvalidArgumentException(\sprintf('No resources found for extension "%s". Available extensions: "%s".', $extensionFilter, implode(', ', $availableExtensions)));
        }

        return $filtered;
    }

    /**
     * Matches the pattern against the name and the URI, so both `symfony-profile*` and
     * `symfony-profiler://*` find the same entries.
     *
     * @param array<string, ResourceData> $resources
     *
     * @return array<string, ResourceData>
     */
    private function filterByPattern(array $resources, string $pattern): array
    {
        $regex = '/^'.str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')).'$/i';

        $filtered = array_filter($resources, static fn (array $resource): bool => 1 === preg_match($regex, $resource['uri']) || (null !== $resource['name'] && 1 === preg_match($regex, $resource['name'])));

        if ([] === $filtered) {
            throw new InvalidArgumentException(\sprintf('No resources found matching pattern "%s".', $pattern));
        }

        return $filtered;
    }

    /**
     * @param array<string, ResourceData> $resources
     */
    private function outputTable(array $resources, SymfonyStyle $io): void
    {
        $io->title('Available Resources');

        if ([] === $resources) {
            $io->warning('No resources found');

            return;
        }

        $table = new Table($io);
        $table->setHeaders(['URI', 'Type', 'Name', 'Description', 'Extension']);

        foreach ($resources as $resource) {
            $table->addRow([
                $resource['uri'],
                $resource['type'],
                $resource['name'] ?? '',
                $resource['description'] ?? '',
                $resource['extension'],
            ]);
        }

        $table->render();

        $io->newLine();
        $io->text(\sprintf('Total: <info>%d</info> resource(s)', \count($resources)));
    }

    /**
     * @param array<string, ResourceData> $resources
     *
     * @return array{resources: list<ResourceData>, summary: array{total: int}}
     */
    private function getArrayResult(array $resources): array
    {
        return [
            'resources' => array_values($resources),
            'summary' => ['total' => \count($resources)],
        ];
    }
}

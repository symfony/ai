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
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Schema\Content\BlobResourceContents;
use Mcp\Schema\Content\ResourceContents;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Request\ReadResourceRequest;
use Symfony\AI\Mate\Command\Session\CliSession;
use Symfony\AI\Mate\Command\Trait\EnsuresToonFormatAvailabilityTrait;
use Symfony\AI\Mate\Service\RegistryProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read MCP resources by URI.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('mcp:resources:read', 'Read MCP resources by URI')]
class ResourcesReadCommand extends Command
{
    use EnsuresToonFormatAvailabilityTrait;

    private RegistryInterface $registry;
    private ReferenceHandler $referenceHandler;

    public function __construct(
        RegistryProvider $registryProvider,
        ContainerInterface $container,
    ) {
        parent::__construct(self::getDefaultName());

        $this->registry = $registryProvider->getRegistry();
        $this->referenceHandler = new ReferenceHandler($container);
    }

    public static function getDefaultName(): string
    {
        return 'mcp:resources:read';
    }

    public static function getDefaultDescription(): string
    {
        return 'Read MCP resources by URI';
    }

    protected function configure(): void
    {
        $script = $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate';

        $this
            ->addArgument('uri', InputArgument::REQUIRED, 'URI of the resource to read')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (pretty, json, toon)', 'pretty')
            ->setHelp(
                <<<HELP
The <info>%command.name%</info> command reads an MCP resource by its URI.

Both static resource URIs and URIs matching a registered resource template are supported.

<info>Usage Examples:</info>

  <comment># Read a static resource</comment>
  %command.full_name% file:///path/to/resource

  <comment># Read a templated resource (URI matched against registered templates)</comment>
  %command.full_name% symfony-profiler://profile/abc123

  <comment># JSON output format</comment>
  %command.full_name% symfony-profiler://profile/abc123 --format=json

  <comment># For a list of available resource templates, use:</comment>
  {$script} debug:capabilities --type=resource
  {$script} debug:capabilities --type=template
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $verbose = $output->isVerbose();

        $uri = $input->getArgument('uri');
        \assert(\is_string($uri));

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if (!$this->ensureToonFormatAvailable($io, $format)) {
            return Command::FAILURE;
        }

        try {
            $reference = $this->registry->getResource($uri);
        } catch (ResourceNotFoundException $e) {
            $io->error(\sprintf('Resource "%s" not found', $uri));
            $io->note(\sprintf('Use "%s debug:capabilities --type=resource" to see all available resources', $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate'));

            return Command::FAILURE;
        }

        $session = new CliSession();
        $request = new ReadResourceRequest(uri: $uri);

        $arguments = [
            'uri' => $uri,
            '_session' => $session,
            '_request' => $request,
        ];

        if ($reference instanceof ResourceTemplateReference) {
            $arguments = array_merge($arguments, $reference->extractVariables($uri));
            $mimeType = $reference->resourceTemplate->mimeType;
            $name = $reference->resourceTemplate->name;
            $description = $reference->resourceTemplate->description;
        } else {
            $mimeType = $reference->resource->mimeType;
            $name = $reference->resource->name;
            $description = $reference->resource->description;
        }

        if ('pretty' === $format) {
            $io->title(\sprintf('Reading Resource: %s', $uri));
            $io->text(\sprintf('<info>Name:</info> %s', $name));
            if (null !== $description) {
                $io->text($description);
            }
            $io->newLine();
        }

        try {
            $result = $this->referenceHandler->handle($reference, $arguments);
            $contents = $reference->formatResult($result, $uri, $mimeType);
        } catch (\Throwable $e) {
            if ($verbose) {
                $io->error(\sprintf('Error: %s', $e->getMessage()));
                $io->text($e->getTraceAsString());
            } else {
                $io->error(\sprintf('Error: %s', $e->getMessage()));
            }

            return Command::FAILURE;
        }

        if ('json' === $format) {
            $output->writeln(json_encode($contents, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ('toon' === $format) {
            $output->writeln(Toon::encode(array_map(static fn ($item) => $item->jsonSerialize(), $contents)));

            return Command::SUCCESS;
        }

        $io->section('Contents');
        foreach ($contents as $content) {
            $this->renderContent($content, $io);
        }

        return Command::SUCCESS;
    }

    private function renderContent(ResourceContents $content, SymfonyStyle $io): void
    {
        $io->definitionList(
            ['URI' => $content->uri],
            ['MIME Type' => $content->mimeType ?? '<comment>N/A</comment>'],
        );

        if ($content instanceof TextResourceContents) {
            $io->text($content->text);

            return;
        }

        if ($content instanceof BlobResourceContents) {
            $io->text(\sprintf('<comment>Binary blob (%d bytes, base64-encoded)</comment>', \strlen($content->blob)));

            return;
        }

        $io->text('<comment>Unknown content type</comment>');
    }
}

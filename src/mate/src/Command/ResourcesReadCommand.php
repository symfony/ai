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
use Symfony\AI\Mate\Command\Trait\EnsuresToonFormatAvailabilityTrait;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Exception\ResourceNotFoundException;
use Symfony\AI\Mate\Invocation\PathResolver;
use Symfony\AI\Mate\Invocation\ResourceReader;
use Symfony\AI\Mate\Invocation\ResultPicker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Read resources by URI.
 *
 * @phpstan-import-type ResourceContent from ResourceReader
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('resources:read', 'Read resources by URI')]
class ResourcesReadCommand extends Command
{
    use EnsuresToonFormatAvailabilityTrait;

    public function __construct(
        private ResourceReader $reader,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'resources:read';
    }

    public static function getDefaultDescription(): string
    {
        return 'Read resources by URI';
    }

    protected function configure(): void
    {
        $script = $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate';

        $this
            ->addArgument('uri', InputArgument::REQUIRED, 'URI of the resource to read')
            ->addOption('pick', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Keep only this path of the resource, repeatable. Supports a.b, [0], [*] and [:3]')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (pretty, json, toon)', 'pretty')
            ->setHelp(
                <<<HELP
The <info>%command.name%</info> command reads a resource by its URI.

Both static resource URIs and URIs matching a registered resource template are supported.

<info>Usage Examples:</info>

  <comment># Read a static resource</comment>
  %command.full_name% file:///path/to/resource

  <comment># Read a templated resource (URI matched against registered templates)</comment>
  %command.full_name% symfony-profiler://profile/abc123

  <comment># JSON output format</comment>
  %command.full_name% symfony-profiler://profile/abc123 --format=json

  <comment># Keep only some paths of a large resource (paths: a.b, [0], [*], [:3], repeatable)</comment>
  %command.full_name% symfony-profiler://profile/abc123/exception --pick=data.class --pick=data.message

  <comment># For a list of available resources and templates, use:</comment>
  {$script} resources:list
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

        if (!$this->ensureFormatSupported($io, $format, ['pretty', 'json', 'toon'])) {
            return Command::FAILURE;
        }

        $picks = array_values(array_filter((array) $input->getOption('pick'), \is_string(...)));
        foreach ($picks as $path) {
            try {
                PathResolver::assertValid($path);
            } catch (InvalidArgumentException $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        try {
            $result = $this->reader->read($uri);
        } catch (ResourceNotFoundException $e) {
            $io->error(\sprintf('Resource "%s" not found', $uri));
            $io->note(\sprintf('Use "%s debug:capabilities --type=template" to see all available resource templates', $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate'));

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error(\sprintf('Error: %s', $e->getMessage()));
            if ($verbose) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }

        $contents = $result['contents'];

        if ([] !== $picks) {
            $contents = $this->pickContents($contents, $picks, $io);
        }

        if ('json' === $format) {
            $output->writeln(json_encode($this->decodeContents($contents), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ('toon' === $format) {
            $output->writeln(Toon::encode($this->decodeContents($contents)));

            return Command::SUCCESS;
        }

        $io->title(\sprintf('Reading Resource: %s', $uri));
        if (null !== $result['name']) {
            $io->text(\sprintf('<info>Name:</info> %s', $result['name']));
        }
        if (null !== $result['description']) {
            $io->text($result['description']);
        }
        $io->newLine();

        $io->section('Contents');
        foreach ($contents as $content) {
            $this->renderContent($content, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * Replaces each structured text content by the picked paths, re-encoded so every output
     * format handles it like an untouched content.
     *
     * @param list<ResourceContent> $contents
     * @param list<string>          $picks
     *
     * @return list<ResourceContent>
     */
    private function pickContents(array $contents, array $picks, SymfonyStyle $io): array
    {
        foreach ($contents as $i => $content) {
            if (!isset($content['text']) || !\is_string($content['text'])) {
                continue;
            }

            $decoded = ResponseEncoder::tryDecode($content['text']);
            if (!\is_array($decoded)) {
                $io->getErrorStyle()->warning(\sprintf('"%s" has no structured content, --pick was ignored.', $content['uri']));

                continue;
            }

            ['result' => $picked, 'missing_paths' => $missing] = ResultPicker::pick($decoded, $picks);
            if ([] !== $missing) {
                $io->getErrorStyle()->warning(\sprintf('Paths without a value: %s', implode(', ', $missing)));
            }

            $contents[$i]['text'] = ResponseEncoder::encode($picked);
        }

        return $contents;
    }

    /**
     * Unwraps an encoded payload so machine-readable output carries structure rather than a
     * JSON document escaped inside a JSON string, which the caller would have to parse twice.
     *
     * @param list<ResourceContent> $contents
     *
     * @return list<array<string, mixed>>
     */
    private function decodeContents(array $contents): array
    {
        $decoded = [];
        foreach ($contents as $content) {
            if (isset($content['text']) && \is_string($content['text'])) {
                $content['text'] = ResponseEncoder::tryDecode($content['text']);
            }

            $decoded[] = $content;
        }

        return $decoded;
    }

    /**
     * @param ResourceContent $content
     */
    private function renderContent(array $content, SymfonyStyle $io): void
    {
        $io->definitionList(
            ['URI' => $content['uri']],
            ['MIME Type' => $content['mimeType'] ?? '<comment>N/A</comment>'],
        );

        if (isset($content['text'])) {
            $io->text($content['text']);

            return;
        }

        if (isset($content['blob'])) {
            $io->text(\sprintf('<comment>Binary blob (%d bytes, base64-encoded)</comment>', \strlen($content['blob'])));

            return;
        }

        $io->text('<comment>Empty content</comment>');
    }
}

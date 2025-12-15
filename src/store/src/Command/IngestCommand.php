<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Command;

use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\IngesterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[AsCommand(
    name: 'ai:store:ingest',
    description: 'Index documents into a store',
)]
final class IngestCommand extends Command
{
    /**
     * @param ServiceLocator<IngesterInterface> $ingesters
     */
    public function __construct(
        private readonly ServiceLocator $ingesters,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('ingester')) {
            $suggestions->suggestValues(array_keys($this->ingesters->getProvidedServices()));
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ingester', InputArgument::REQUIRED, 'Name of the ingester to run')
            ->addOption('source', 's', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Source(s) to ingest (overrides configured source)')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command ingestes documents into a store using the specified ingester.

Basic usage:
    <info>php %command.full_name% blog</info>

Override the configured source with a single source:
    <info>php %command.full_name% blog --source=/path/to/file.txt</info>

Override with multiple sources:
    <info>php %command.full_name% blog --source=/path/to/file1.txt --source=/path/to/file2.txt</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ingester = $input->getArgument('ingester');
        $sources = $input->getOption('source');
        $source = match (true) {
            [] === $sources => null,
            1 === \count($sources) => $sources[0],
            default => $sources,
        };

        if (!$this->ingesters->has($ingester)) {
            throw new RuntimeException(\sprintf('The "%s" ingester does not exist.', $ingester));
        }

        $ingesterService = $this->ingesters->get($ingester);

        if (null !== $source) {
            $ingesterService = $ingesterService->withSource($source);
        }

        $io->title(\sprintf('Indexing documents using "%s" ingester', $ingester));

        try {
            $ingesterService->ingest($source);

            $io->success(\sprintf('Documents ingested successfully using "%s" ingester.', $ingester));
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('An error occurred while ingesting with "%s": ', $ingester).$e->getMessage(), previous: $e);
        }

        return Command::SUCCESS;
    }
}

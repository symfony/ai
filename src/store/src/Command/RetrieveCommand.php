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
use Symfony\AI\Store\RetrieverInterface;
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
    name: 'ai:store:retrieve',
    description: 'Retrieve documents from a store',
)]
final class RetrieveCommand extends Command
{
    /**
     * @param ServiceLocator<RetrieverInterface> $retrievers
     */
    public function __construct(
        private readonly ServiceLocator $retrievers,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('retriever')) {
            $suggestions->suggestValues(array_keys($this->retrievers->getProvidedServices()));
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('retriever', InputArgument::REQUIRED, 'Name of the retriever to use')
            ->addArgument('query', InputArgument::OPTIONAL, 'Search query')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of results to return', '10')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command retrieves documents from a store using the specified retriever.

Basic usage:
    <info>php %command.full_name% blog "search query"</info>

Interactive mode (prompts for query):
    <info>php %command.full_name% blog</info>

Limit results:
    <info>php %command.full_name% blog "search query" --limit=5</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $retriever = $input->getArgument('retriever');

        if (!$this->retrievers->has($retriever)) {
            throw new RuntimeException(\sprintf('The "%s" retriever does not exist.', $retriever));
        }

        $query = $input->getArgument('query');
        if (null === $query) {
            $query = $io->ask('What do you want to search for?');
            if (null === $query || '' === $query) {
                $io->error('A search query is required.');

                return Command::FAILURE;
            }
        }

        $limit = (int) $input->getOption('limit');

        $io->title(\sprintf('Retrieving documents using "%s" retriever', $retriever));
        $io->comment(\sprintf('Searching for: "%s"', $query));

        try {
            $retrieverService = $this->retrievers->get($retriever);
            $documents = $retrieverService->retrieve($query, ['maxItems' => $limit]);

            $count = 0;
            foreach ($documents as $document) {
                ++$count;
                $io->section(\sprintf('Result #%d', $count));

                $tableData = [
                    ['ID', (string) $document->id],
                    ['Score', $document->score ?? 'n/a'],
                ];

                if ($document->metadata->hasSource()) {
                    $tableData[] = ['Source', $document->metadata->getSource()];
                }

                if ($document->metadata->hasText()) {
                    $text = $document->metadata->getText();
                    if (\strlen($text) > 200) {
                        $text = substr($text, 0, 200).'...';
                    }
                    $tableData[] = ['Text', $text];
                }

                $io->table([], $tableData);

                if ($count >= $limit) {
                    break;
                }
            }

            if (0 === $count) {
                $io->warning('No results found.');

                return Command::SUCCESS;
            }

            $io->success(\sprintf('Found %d result(s) using "%s" retriever.', $count, $retriever));
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('An error occurred while retrieving with "%s": ', $retriever).$e->getMessage(), previous: $e);
        }

        return Command::SUCCESS;
    }
}

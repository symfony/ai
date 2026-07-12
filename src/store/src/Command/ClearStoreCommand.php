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
use Symfony\AI\Store\StoreInterface;
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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand(name: 'ai:store:clear', description: 'Remove all documents from the store')]
final class ClearStoreCommand extends Command
{
    /**
     * @param ServiceLocator<StoreInterface> $stores
     */
    public function __construct(
        private readonly ServiceLocator $stores,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('store')) {
            $suggestions->suggestValues($this->getStoreNames());
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('store', InputArgument::REQUIRED, 'Service name of the store to clear')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force clearing the store, required to actually remove the documents')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command removes all documents from a store, but keeps the store usable.
Since this cannot be undone, the <info>--force</info> option is required:

    <info>php %command.full_name% <store> --force</info>

In contrast to <info>ai:store:drop</info>, documents can be indexed again afterwards without running
<info>ai:store:setup</info> first.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (0 === \count($this->getStoreNames())) {
            throw new RuntimeException('No store is configured to be cleared.');
        }

        $storeName = $input->getArgument('store');
        if (!$this->stores->has($storeName)) {
            throw new RuntimeException(\sprintf('The "%s" store does not exist, use "%s".', $storeName, implode('", "', $this->getStoreNames())));
        }

        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $io->warning('The --force option is required to clear the store.');

            return Command::FAILURE;
        }

        $store = $this->stores->get($storeName);

        try {
            $store->clear();
            $io->success(\sprintf('The "%s" store was cleared successfully.', $storeName));
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('An error occurred while clearing the "%s" store: ', $storeName).$e->getMessage(), previous: $e);
        }

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getStoreNames(): array
    {
        return array_keys($this->stores->getProvidedServices());
    }
}

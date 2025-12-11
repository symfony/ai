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
use Symfony\AI\Store\ManagedStoreInterface;
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
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
#[AsCommand(name: 'ai:store:drop', description: 'Drop the required infrastructure for the store')]
final class DropStoreCommand extends Command
{
    /**
     * @param ServiceLocator<ManagedStoreInterface> $stores
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
            ->addArgument('store', InputArgument::REQUIRED, 'Service name of the store to drop')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force dropping the store even if it contains messages')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command drops the stores:

    <info>php %command.full_name%</info>

Or a specific store only:

    <info>php %command.full_name% <store></info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (0 === \count($this->getStoreNames())) {
            throw new RuntimeException('No store is configured to be dropped.');
        }

        $storeName = $input->getArgument('store');
        if (!$this->stores->has($storeName)) {
            throw new RuntimeException(\sprintf('The "%s" store does not exist, use "%s".', $storeName, implode('", "', $this->getStoreNames())));
        }

        $store = $this->stores->get($storeName);
        if (!$store instanceof ManagedStoreInterface) {
            throw new RuntimeException(\sprintf('The "%s" store does not support to be dropped.', $storeName));
        }

        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $io->warning('The --force option is required to drop the store.');

            return Command::FAILURE;
        }

        $storeName = $input->getArgument('store');

        $store = $this->stores->get($storeName);

        try {
            $store->drop();
            $io->success(\sprintf('The "%s" store was dropped successfully.', $storeName));
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('An error occurred while dropping the "%s" store: ', $storeName).$e->getMessage(), previous: $e);
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

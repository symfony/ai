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
use Symfony\AI\Store\TraceableStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
#[AsCommand(name: 'ai:store:setup', description: 'Prepare the required infrastructure for the store')]
final class SetupStoreCommand extends Command
{
    use ServiceArgumentTrait;
    use StoreOptionsTrait;

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
            ->addArgument('store', InputArgument::REQUIRED, 'Service name of the store to setup')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command sets up a store:

    <info>php %command.full_name% <store></info>

Pass runtime options supported by the store with <comment>--option</comment>:

    <info>php %command.full_name% <store> --option forceDatabaseCreation=true</info>
EOF
            )
        ;
        $this->addStoreOptionsOption();
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->interactForService($input, $output, 'store', 'store', $this->getStoreNames());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (0 === \count($this->getStoreNames())) {
            throw new RuntimeException('No store is configured to be set up.');
        }

        $storeName = $this->resolveServiceName($input->getArgument('store'), 'store', $this->getStoreNames());

        $store = $this->stores->get($storeName);
        if ($store instanceof TraceableStore) {
            $store = $store->getDecoratedStore();
        }
        if (!$store instanceof ManagedStoreInterface) {
            throw new RuntimeException(\sprintf('The "%s" store does not support setup.', $storeName));
        }

        $io = new SymfonyStyle($input, $output);
        $options = $this->getStoreOptions($input);

        try {
            $store->setup($options);
            $io->success(\sprintf('The "%s" store was set up successfully.', $storeName));
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('An error occurred while setting up the "%s" store: ', $storeName).$e->getMessage(), previous: $e);
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

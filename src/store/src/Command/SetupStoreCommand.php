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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
#[AsCommand(name: 'ai:store:setup', description: 'Prepare the required infrastructure for the store')]
final class SetupStoreCommand extends Command
{
    /**
     * @param ServiceLocator<ManagedStoreInterface> $stores
     * @param array<string, mixed>                  $setupStoresOptions
     */
    public function __construct(
        private readonly ServiceLocator $stores,
        private readonly array $setupStoresOptions = [],
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('store')) {
            $suggestions->suggestValues(array_keys($this->stores->getProvidedServices()));
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('store', InputArgument::REQUIRED, 'Name of the store to setup')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command setups the stores:

    <info>php %command.full_name%</info>

Or a specific store only:

    <info>php %command.full_name% <store></info>
EOF
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $storeName = $input->getArgument('store');
        if (!$this->stores->has($storeName)) {
            throw new RuntimeException(\sprintf('The "%s" store does not exist.', $storeName));
        }

        $store = $this->stores->get($storeName);
        if (!$store instanceof ManagedStoreInterface) {
            throw new RuntimeException(\sprintf('The "%s" store does not support setup.', $storeName));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $storeName = $input->getArgument('store');

        $store = $this->stores->get($storeName);

        try {
            $store->setup($this->setupStoresOptions[$storeName] ?? []);
            $io->success(\sprintf('The "%s" store was set up successfully.', $storeName));
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('An error occurred while setting up the "%s" store: ', $storeName).$e->getMessage(), previous: $e);
        }

        return Command::SUCCESS;
    }
}

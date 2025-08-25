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

use Psr\Container\ContainerInterface;
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

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
#[AsCommand(name: 'ai:store:setup', description: 'Prepare the required infrastructure for the store')]
final class SetupStoreCommand extends Command
{
    /**
     * @param string[] $storeNames
     */
    public function __construct(
        private readonly ContainerInterface $storeLocator,
        private readonly array $storeNames = [],
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('store')) {
            $suggestions->suggestValues($this->storeNames);
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('store', InputArgument::OPTIONAL, 'Name of the store to setup')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command setups the stores:

    <info>php %command.full_name%</info>

Or a specific store only:

    <info>php %command.full_name% <store></info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $storeName = $input->getArgument('store');
        if (!$this->storeLocator->has($storeName)) {
            throw new RuntimeException(\sprintf('The "%s" store does not exist.', $storeName));
        }

        $store = $this->storeLocator->get($storeName);
        if (!$store instanceof ManagedStoreInterface) {
            $io->note(\sprintf('The "%s" store does not support setup.', $storeName));

            return Command::FAILURE;
        }

        try {
            $store->setup();
            $io->success(\sprintf('The "%s" store was set up successfully.', $storeName));
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('An error occurred while setting up the "%s" store: ', $storeName).$e->getMessage(), 0, $e);
        }

        return Command::SUCCESS;
    }
}

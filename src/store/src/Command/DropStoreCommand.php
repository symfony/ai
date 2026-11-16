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
            ->addArgument('store', InputArgument::REQUIRED, 'Service name of the store to drop')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force dropping the store even if it contains vectors')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command drops a store:

    <info>php %command.full_name% <store> --force</info>

Pass runtime options supported by the store with <comment>--option</comment>:

    <info>php %command.full_name% <store> --force --option optimize</info>
EOF
            )
        ;
        $this->addStoreOptionsOption();
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->interactForService($input, $output, 'store', 'store', $this->getStoreNames());

        if (!$input->getOption('force') && null !== $input->getArgument('store')) {
            $question = \sprintf('Do you really want to drop the "%s" store?', $input->getArgument('store'));
            $input->setOption('force', (new SymfonyStyle($input, $output))->confirm($question, false));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (0 === \count($this->getStoreNames())) {
            throw new RuntimeException('No store is configured to be dropped.');
        }

        $storeName = $this->resolveServiceName($input->getArgument('store'), 'store', $this->getStoreNames());

        $store = $this->stores->get($storeName);
        if (!$store instanceof ManagedStoreInterface) {
            throw new RuntimeException(\sprintf('The "%s" store does not support to be dropped.', $storeName));
        }

        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $io->warning('The --force option is required to drop the store.');

            return Command::FAILURE;
        }

        $options = $this->getStoreOptions($input);

        try {
            $store->drop($options);
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

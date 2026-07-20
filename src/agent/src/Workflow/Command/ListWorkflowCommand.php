<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Command;

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Workflow\ListableWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\TraceableWorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
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
#[AsCommand(name: 'ai:workflow:list', description: 'List the persisted states of a workflow')]
final class ListWorkflowCommand extends Command
{
    /**
     * @param ServiceLocator<WorkflowStateStoreInterface> $stores
     */
    public function __construct(
        private readonly ServiceLocator $stores,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('workflow')) {
            $suggestions->suggestValues($this->getWorkflowNames());
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('workflow', InputArgument::REQUIRED, 'Name of the workflow whose persisted states to list')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists the persisted states of a workflow:

    <info>php %command.full_name% <workflow></info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (0 === \count($this->getWorkflowNames())) {
            throw new RuntimeException('No workflow is configured.');
        }

        $workflowName = $input->getArgument('workflow');
        if (!$this->stores->has($workflowName)) {
            throw new RuntimeException(\sprintf('The "%s" workflow does not exist, use "%s".', $workflowName, implode('", "', $this->getWorkflowNames())));
        }

        $store = $this->stores->get($workflowName);
        if ($store instanceof TraceableWorkflowStateStore) {
            $store = $store->getDecoratedStore();
        }
        if (!$store instanceof ListableWorkflowStateStoreInterface) {
            throw new RuntimeException(\sprintf('The state store of the "%s" workflow does not support listing.', $workflowName));
        }

        $io = new SymfonyStyle($input, $output);

        $rows = [];
        foreach ($store->list() as $id) {
            $rows[] = [$id];
        }

        if ([] === $rows) {
            $io->info(\sprintf('No state is currently persisted for the "%s" workflow.', $workflowName));

            return Command::SUCCESS;
        }

        $io->title(\sprintf('Persisted states of the "%s" workflow', $workflowName));
        $io->table(['State ID'], $rows);
        $io->comment(\sprintf('%d state(s) found.', \count($rows)));

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getWorkflowNames(): array
    {
        return array_keys($this->stores->getProvidedServices());
    }
}

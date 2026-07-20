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
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\ListableWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\TraceableWorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
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
#[AsCommand(name: 'ai:workflow:prune', description: 'Delete persisted workflow states older than a given age')]
final class PruneWorkflowCommand extends Command
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
            ->addArgument('workflow', InputArgument::REQUIRED, 'Name of the workflow whose old states to prune')
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Delete states last updated before this relative date', '30 days')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Confirm the deletion of the matching states')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command deletes the persisted states of a workflow that have not
been updated recently:

    <info>php %command.full_name% <workflow> --older-than="7 days" --force</info>

Without <info>--force</info> the command only reports how many states would be deleted.
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
            throw new RuntimeException(\sprintf('The state store of the "%s" workflow does not support pruning.', $workflowName));
        }

        $io = new SymfonyStyle($input, $output);

        $olderThan = (string) $input->getOption('older-than');
        try {
            $threshold = new \DateTimeImmutable('-'.$olderThan);
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('The "--older-than" value "%s" is not a valid relative date.', $olderThan), previous: $e);
        }

        $stale = [];
        foreach ($store->list() as $id) {
            try {
                $updatedAt = $store->load($id)->getUpdatedAt();
            } catch (WorkflowStateNotFoundException) {
                // The state vanished between listing and loading (expiry or a concurrent delete);
                // skip it rather than aborting the prune of every other state.
                continue;
            }

            if (null !== $updatedAt && $updatedAt < $threshold) {
                $stale[] = $id;
            }
        }

        if ([] === $stale) {
            $io->info(\sprintf('No state of the "%s" workflow is older than "%s".', $workflowName, $olderThan));

            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            $io->warning(\sprintf('%d state(s) would be deleted. Re-run with --force to proceed.', \count($stale)));

            return Command::FAILURE;
        }

        try {
            foreach ($stale as $id) {
                $store->delete($id);
            }
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('An error occurred while pruning the "%s" workflow: ', $workflowName).$e->getMessage(), previous: $e);
        }

        $io->success(\sprintf('Pruned %d state(s) of the "%s" workflow older than "%s".', \count($stale), $workflowName, $olderThan));

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

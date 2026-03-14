<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Command;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Skill\Evaluation\Aggregator\BenchmarkAggregatorInterface;
use Symfony\AI\Agent\Skill\Evaluation\EvalRunResult;
use Symfony\AI\Agent\Skill\Evaluation\EvalSuite;
use Symfony\AI\Agent\Skill\Evaluation\EvalSuiteLoaderInterface;
use Symfony\AI\Agent\Skill\Evaluation\Grader\GraderInterface;
use Symfony\AI\Agent\Skill\Evaluation\Runner\EvalRunner;
use Symfony\AI\Agent\Skill\Evaluation\Workspace\WorkspaceManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'ai:agent:eval-skill',
    description: 'Evaluate an Agent Skill using its evals.json test suite',
)]
final class EvalSkillCommand extends Command
{
    /**
     * @param ServiceLocator<AgentInterface> $agentLocator
     */
    public function __construct(
        private readonly EvalSuiteLoaderInterface $evalSuiteLoader,
        private readonly WorkspaceManagerInterface $workspaceManager,
        private readonly BenchmarkAggregatorInterface $benchmarkAggregator,
        private readonly ClockInterface $clock,
        private readonly ServiceLocator $agentLocator,
        private readonly ?GraderInterface $grader = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('skill-directory', InputArgument::REQUIRED, 'Path to the skill directory')
            ->addArgument('workspace', InputArgument::OPTIONAL, 'Path to the workspace directory')
            ->addOption('iteration', 'i', InputOption::VALUE_REQUIRED, 'Iteration number', '1')
            ->addOption('agent', null, InputOption::VALUE_REQUIRED, 'Agent service name for with-skill runs')
            ->addOption('baseline-agent', null, InputOption::VALUE_REQUIRED, 'Agent service name for without-skill (baseline) runs')
            ->addOption('skip-grading', null, InputOption::VALUE_NONE, 'Skip LLM grading')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $skillDirectory = $input->getArgument('skill-directory');
        $iteration = (int) $input->getOption('iteration');
        $skipGrading = $input->getOption('skip-grading');

        try {
            $suite = $this->evalSuiteLoader->load($skillDirectory);
        } catch (\Throwable $e) {
            $io->error(\sprintf('Failed to load eval suite: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->title(\sprintf('Evaluating skill: %s', $suite->getSkillName()));
        $io->writeln(\sprintf('Found %d eval case(s)', \count($suite->getEvals())));

        $agentName = $input->getOption('agent');
        $baselineAgentName = $input->getOption('baseline-agent');

        if (null === $agentName) {
            $io->error('The --agent option is required.');

            return Command::FAILURE;
        }

        if (!$this->agentLocator->has($agentName)) {
            $io->error(\sprintf('Agent "%s" not found.', $agentName));

            return Command::FAILURE;
        }

        $this->workspaceManager->initializeIteration($iteration);

        $withSkillResults = $this->runEvals($io, $suite, $agentName, $iteration, 'with_skill', $skipGrading);

        $withoutSkillResults = [];
        if (null !== $baselineAgentName) {
            if (!$this->agentLocator->has($baselineAgentName)) {
                $io->error(\sprintf('Baseline agent "%s" not found.', $baselineAgentName));

                return Command::FAILURE;
            }

            $withoutSkillResults = $this->runEvals($io, $suite, $baselineAgentName, $iteration, 'without_skill', $skipGrading);
        }

        if ([] !== $withSkillResults && [] !== $withoutSkillResults) {
            $benchmark = $this->benchmarkAggregator->aggregate($withSkillResults, $withoutSkillResults);
            $this->workspaceManager->saveBenchmarkResult($iteration, $benchmark);

            $io->section('Benchmark Results');

            $delta = $benchmark->getDelta();
            $io->table(
                ['Metric', 'With Skill', 'Without Skill', 'Delta'],
                [
                    ['Pass Rate', \sprintf('%.2f', $benchmark->getWithSkillPassRate()->getMean()), \sprintf('%.2f', $benchmark->getWithoutSkillPassRate()->getMean()), \sprintf('%+.2f', $delta['pass_rate'])],
                    ['Time (ms)', \sprintf('%.0f', $benchmark->getWithSkillTime()->getMean()), \sprintf('%.0f', $benchmark->getWithoutSkillTime()->getMean()), \sprintf('%+.0f', $delta['time_ms'])],
                    ['Tokens', \sprintf('%.0f', $benchmark->getWithSkillTokens()->getMean()), \sprintf('%.0f', $benchmark->getWithoutSkillTokens()->getMean()), \sprintf('%+.0f', $delta['tokens'])],
                ],
            );
        }

        $io->success(\sprintf('Evaluation complete. Results saved to iteration-%d.', $iteration));

        return Command::SUCCESS;
    }

    /**
     * @return EvalRunResult[]
     */
    private function runEvals(SymfonyStyle $io, EvalSuite $suite, string $agentName, int $iteration, string $configuration, bool $skipGrading): array
    {
        /** @var AgentInterface $agent */
        $agent = $this->agentLocator->get($agentName);
        $runner = new EvalRunner($agent, $this->clock);

        $io->section(\sprintf('Running %s evals with agent "%s"', $configuration, $agentName));

        $results = [];
        foreach ($suite->getEvals() as $evalCase) {
            $io->write(\sprintf('  Eval #%d: %s ... ', $evalCase->getId(), mb_substr($evalCase->getPrompt(), 0, 50)));

            $runResult = $runner->run($evalCase);

            $evalDir = $this->workspaceManager->getEvalDirectory($iteration, (string) $evalCase->getId(), $configuration);
            $this->workspaceManager->saveOutput($evalDir, $runResult->getOutput());
            $this->workspaceManager->saveTimingResult($evalDir, $runResult->getTiming());

            if (!$skipGrading && null !== $this->grader && [] !== $evalCase->getAssertions()) {
                $grading = $this->grader->grade($runResult->getOutput(), $evalCase->getAssertions(), $evalCase->getExpectedOutput());
                $runResult = $runResult->withGrading($grading);
                $this->workspaceManager->saveGradingResult($evalDir, $grading);

                $summary = $grading->getSummary();
                $io->writeln(\sprintf('<info>%d/%d passed</info> (%dms, %d tokens)',
                    $summary['passed'], $summary['total'],
                    $runResult->getTiming()->getDurationMs(),
                    $runResult->getTiming()->getTotalTokens(),
                ));
            } else {
                $io->writeln(\sprintf('done (%dms, %d tokens)',
                    $runResult->getTiming()->getDurationMs(),
                    $runResult->getTiming()->getTotalTokens(),
                ));
            }

            $results[] = $runResult;
        }

        return $results;
    }
}

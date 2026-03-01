<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Workflow\Bridge\SymfonyWorkflowAdapter;
use Symfony\AI\Agent\Workflow\Event\TransitionEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowFailedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowStartedEvent;
use Symfony\AI\Agent\Workflow\Step\StepExecutorInterface;
use Symfony\AI\Agent\Workflow\Step\StepInterface;
use Symfony\AI\Agent\Workflow\Transition\TransitionInterface;
use Symfony\AI\Agent\Workflow\Transition\TransitionRegistryInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowExecutor implements WorkflowExecutorInterface
{
    /** @var StepInterface[] */
    private array $steps = [];

    private readonly SymfonyWorkflowAdapter $workflowAdapter;

    public function __construct(
        private readonly TransitionRegistryInterface $transitionRegistry,
        private readonly WorkflowStoreInterface $store,
        private readonly ?StepExecutorInterface $stepExecutor = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $maxExecutionTime = 300, // 5 minutes
        private readonly string $initialPlace = 'start',
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
        $this->workflowAdapter = new SymfonyWorkflowAdapter($this->transitionRegistry, $this->initialPlace);
    }

    public function addStep(StepInterface $step): void
    {
        $this->steps[$step->getName()] = $step;
    }

    public function execute(AgentInterface $agent, WorkflowStateInterface $state, array $options = []): ResultInterface
    {
        $startTime = $this->clock->now();

        try {
            $state->setStatus(WorkflowStatus::RUNNING);
            $this->store->save($state);

            $this->eventDispatcher?->dispatch(new WorkflowStartedEvent($state));
            $this->logger->info('Workflow started', ['id' => $state->getId()]);

            $result = $this->executeSteps($agent, $state, $startTime);

            $state->setStatus(WorkflowStatus::COMPLETED);
            $this->store->save($state);

            $this->eventDispatcher?->dispatch(new WorkflowCompletedEvent($state));
            $this->logger->info('Workflow completed', ['id' => $state->getId()]);

            return $result;
        } catch (\Throwable $e) {
            $this->handleError($state, $e);

            throw $e;
        }
    }

    public function resume(string $id): ResultInterface
    {
        $state = $this->store->load($id);

        if (null === $state) {
            throw new RuntimeException(\sprintf('Workflow with ID "%s" not found', $id));
        }

        if (WorkflowStatus::COMPLETED === $state->getStatus()) {
            throw new RuntimeException(\sprintf('Workflow "%s" is already completed', $id));
        }

        if (WorkflowStatus::FAILED === $state->getStatus()) {
            $state->clearErrors();
        }

        $this->logger->info('Resuming workflow', [
            'id' => $id,
            'step' => $state->getCurrentStep(),
        ]);

        throw new \RuntimeException('Resume requires an agent instance');
    }

    private function executeSteps(AgentInterface $agent, WorkflowStateInterface $state, \DateTimeImmutable $startTime): ResultInterface
    {
        $currentStep = $state->getCurrentStep();
        $result = null;

        while (null !== $currentStep) {
            $this->checkExecutionTime($startTime);

            if (!isset($this->steps[$currentStep])) {
                throw new RuntimeException(\sprintf('Step "%s" not found', $currentStep));
            }

            $step = $this->steps[$currentStep];

            $this->logger->debug('Executing step', [
                'step' => $currentStep,
                'parallel' => $step->isParallel(),
            ]);

            try {
                $results = $this->stepExecutor->execute([$step], $agent, $state);

                $result = reset($results);

                $state->mergeContext([
                    'last_result' => $result->getContent(),
                    'last_step' => $currentStep,
                ]);

                $this->store->save($state);
            } catch (\Throwable $e) {
                $error = new WorkflowError(
                    $e->getMessage(),
                    $currentStep,
                    $e->getCode(),
                    $e,
                    context: ['trace' => $e->getTraceAsString()]
                );

                $state->addError($error);
                $this->store->save($state);

                throw $e;
            }

            $enabledTransitions = $this->workflowAdapter->getEnabledTransitions($state);

            if ([] === $enabledTransitions) {
                break;
            }

            $transitionName = reset($enabledTransitions);

            $this->logger->debug('Applying Symfony Workflow transition', [
                'transition' => $transitionName,
                'from' => $currentStep,
            ]);

            $transition = $this->transitionRegistry->getTransition($transitionName);
            if ($transition) {
                $transition->beforeTransition($state);
            }

            $this->workflowAdapter->apply($state, $transitionName);

            if ($transition instanceof TransitionInterface) {
                $transition->afterTransition($state);
                $this->eventDispatcher?->dispatch(new TransitionEvent($state, $transition));
            }

            $this->store->save($state);
            $currentStep = $state->getCurrentStep();
        }

        return $result ?? throw new \RuntimeException('No result from workflow');
    }

    private function handleError(WorkflowStateInterface $state, \Throwable $e): void
    {
        $error = new WorkflowError(
            $e->getMessage(),
            $state->getCurrentStep(),
            $e->getCode(),
            $e,
            context: ['trace' => $e->getTraceAsString()]
        );

        $state->addError($error);
        $state->setStatus(WorkflowStatus::FAILED);
        $this->store->save($state);

        $this->eventDispatcher?->dispatch(new WorkflowFailedEvent($state, $e));
        $this->logger->error('Workflow failed', [
            'id' => $state->getId(),
            'error' => $e->getMessage(),
        ]);
    }

    private function checkExecutionTime(float $startTime): void
    {
        if (microtime(true) - $startTime > $this->maxExecutionTime) {
            throw new \RuntimeException(\sprintf('Workflow execution exceeded maximum time of %d seconds', $this->maxExecutionTime));
        }
    }
}

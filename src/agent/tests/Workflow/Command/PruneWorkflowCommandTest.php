<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Workflow\Command\PruneWorkflowCommand;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\ListableWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\TraceableWorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class PruneWorkflowCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new PruneWorkflowCommand(new ServiceLocator([]));

        $this->assertSame('ai:workflow:prune', $command->getName());
        $this->assertSame('Delete persisted workflow states older than a given age', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('workflow'));
        $this->assertTrue($definition->hasOption('older-than'));
        $this->assertTrue($definition->hasOption('force'));

        $workflowArg = $definition->getArgument('workflow');
        $this->assertTrue($workflowArg->isRequired());

        $olderThanOption = $definition->getOption('older-than');
        $this->assertSame('30 days', $olderThanOption->getDefault());
    }

    public function testCommandThrowsWhenNoWorkflowIsConfigured()
    {
        $command = new PruneWorkflowCommand(new ServiceLocator([]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No workflow is configured.');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandThrowsWhenWorkflowNameIsUnknown()
    {
        $command = new PruneWorkflowCommand(new ServiceLocator([
            'other' => static fn (): object => new WorkflowStateStore(),
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "unknown" workflow does not exist, use "other".');

        $tester->execute(['workflow' => 'unknown']);
    }

    public function testCommandThrowsWhenStoreDoesNotSupportPruning()
    {
        $store = $this->createMock(WorkflowStateStoreInterface::class);

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The state store of the "my_workflow" workflow does not support pruning.');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandThrowsWhenTraceableWrapsNonListableStore()
    {
        $nonListable = new class implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface {
            public function setup(): void
            {
            }

            public function drop(): void
            {
            }

            public function save(WorkflowStateInterface $state): void
            {
            }

            public function load(string $id): WorkflowStateInterface
            {
                throw new \LogicException('Not implemented.');
            }

            public function has(string $id): bool
            {
                return false;
            }

            public function delete(string $id): void
            {
            }
        };

        $traceable = new TraceableWorkflowStateStore($nonListable);

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $traceable,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The state store of the "my_workflow" workflow does not support pruning.');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandThrowsOnInvalidOlderThanValue()
    {
        $store = new ControlledStateStore();

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a valid relative date');

        $tester->execute(['workflow' => 'my_workflow', '--older-than' => 'not a date at all!!!']);
    }

    public function testCommandWithForceDeletesStaleStatesAndKeepsRecentOnes()
    {
        $store = new ControlledStateStore();

        $staleState = new WorkflowState(
            'stale-state',
            [],
            [],
            null,
            null,
            [],
            new \DateTimeImmutable('60 days ago'),
        );
        $recentState = new WorkflowState(
            'recent-state',
            [],
            [],
            null,
            null,
            [],
            new \DateTimeImmutable('1 day ago'),
        );

        $store->addState($staleState);
        $store->addState($recentState);

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($store->has('stale-state'));
        $this->assertTrue($store->has('recent-state'));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('my_workflow', $display);
    }

    public function testCommandWithoutForceReportsCountAndReturnsFailure()
    {
        $store = new ControlledStateStore();

        $staleState = new WorkflowState(
            'stale-1',
            [],
            [],
            null,
            null,
            [],
            new \DateTimeImmutable('90 days ago'),
        );
        $anotherStaleState = new WorkflowState(
            'stale-2',
            [],
            [],
            null,
            null,
            [],
            new \DateTimeImmutable('45 days ago'),
        );

        $store->addState($staleState);
        $store->addState($anotherStaleState);

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow']);

        $this->assertSame(Command::FAILURE, $exitCode);
        // Both states must still be present (no deletion without --force)
        $this->assertTrue($store->has('stale-1'));
        $this->assertTrue($store->has('stale-2'));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('2', $display);
        $this->assertStringContainsString('--force', $display);
    }

    public function testCommandSkipsStatesWithNullUpdatedAt()
    {
        $store = new ControlledStateStore();

        // updatedAt is null: the command must skip it
        $nullUpdatedAtState = new WorkflowState('no-date-state');
        $store->addState($nullUpdatedAtState);

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', '--force' => true]);

        // No stale states found → SUCCESS with informational message, nothing deleted
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertTrue($store->has('no-date-state'));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No state', $display);
    }

    public function testCommandUsesCustomOlderThanThreshold()
    {
        $store = new ControlledStateStore();

        // Older than 7 days
        $oldState = new WorkflowState(
            'old-state',
            [],
            [],
            null,
            null,
            [],
            new \DateTimeImmutable('10 days ago'),
        );
        // Only 3 days old, should survive a 7-day threshold
        $freshState = new WorkflowState(
            'fresh-state',
            [],
            [],
            null,
            null,
            [],
            new \DateTimeImmutable('3 days ago'),
        );

        $store->addState($oldState);
        $store->addState($freshState);

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', '--older-than' => '7 days', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($store->has('old-state'));
        $this->assertTrue($store->has('fresh-state'));
    }

    public function testCommandReturnsSuccessWhenNoStatesAreStale()
    {
        $store = new ControlledStateStore();

        $recentState = new WorkflowState(
            'brand-new',
            [],
            [],
            null,
            null,
            [],
            new \DateTimeImmutable('1 hour ago'),
        );
        $store->addState($recentState);

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertTrue($store->has('brand-new'));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No state', $display);
    }

    public function testCommandUnwrapsTraceableWorkflowStateStore()
    {
        $inner = new WorkflowStateStore();
        // We need a state with a non-null updatedAt; save it and then replace via
        // a controlled store to keep the test simple
        $store = new ControlledStateStore();
        $staleState = new WorkflowState(
            'stale-traceable',
            [],
            [],
            null,
            null,
            [],
            new \DateTimeImmutable('40 days ago'),
        );
        $store->addState($staleState);

        // TraceableWorkflowStateStore constructor requires the inner store to implement
        // both WorkflowStateStoreInterface and ManagedWorkflowStateStoreInterface.
        // ControlledStateStore already implements both, so wrap it directly.
        $traceable = new TraceableWorkflowStateStore($store);

        $command = new PruneWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $traceable,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($store->has('stale-traceable'));
    }
}

// ---------------------------------------------------------------------------
// Test double store — defined at file scope to avoid anonymous-class PHPStan issues
// ---------------------------------------------------------------------------

/**
 * A fully controllable state store that holds WorkflowStateInterface instances directly,
 * allowing tests to supply explicit updatedAt values without going through the normalizer.
 *
 * @internal
 */
final class ControlledStateStore implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface, ListableWorkflowStateStoreInterface
{
    /** @var array<string, WorkflowStateInterface> */
    private array $states = [];

    public function setup(): void
    {
    }

    public function addState(WorkflowStateInterface $state): void
    {
        $this->states[$state->getId()] = $state;
    }

    public function drop(): void
    {
        $this->states = [];
    }

    public function save(WorkflowStateInterface $state): void
    {
        $this->states[$state->getId()] = $state;
    }

    public function load(string $id): WorkflowStateInterface
    {
        if (!isset($this->states[$id])) {
            throw new \LogicException(\sprintf('State "%s" not found.', $id));
        }

        return $this->states[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->states[$id]);
    }

    public function delete(string $id): void
    {
        unset($this->states[$id]);
    }

    public function list(): iterable
    {
        return array_keys($this->states);
    }
}

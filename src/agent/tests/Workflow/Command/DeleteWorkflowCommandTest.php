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
use Symfony\AI\Agent\Workflow\Command\DeleteWorkflowCommand;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
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
final class DeleteWorkflowCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new DeleteWorkflowCommand(new ServiceLocator([]));

        $this->assertSame('ai:workflow:delete', $command->getName());
        $this->assertSame('Delete a single persisted workflow state', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('workflow'));
        $this->assertTrue($definition->hasArgument('id'));

        $workflowArg = $definition->getArgument('workflow');
        $this->assertTrue($workflowArg->isRequired());

        $idArg = $definition->getArgument('id');
        $this->assertTrue($idArg->isRequired());
    }

    public function testCommandThrowsWhenNoWorkflowIsConfigured()
    {
        $command = new DeleteWorkflowCommand(new ServiceLocator([]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No workflow is configured.');

        $tester->execute(['workflow' => 'my_workflow', 'id' => 'state-1']);
    }

    public function testCommandThrowsWhenWorkflowNameIsUnknown()
    {
        $command = new DeleteWorkflowCommand(new ServiceLocator([
            'other' => static fn (): object => new WorkflowStateStore(),
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "unknown" workflow does not exist, use "other".');

        $tester->execute(['workflow' => 'unknown', 'id' => 'state-1']);
    }

    public function testCommandWarnsAndFailsWhenStateIdDoesNotExist()
    {
        $store = new WorkflowStateStore();

        $command = new DeleteWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', 'id' => 'nonexistent']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('nonexistent', $tester->getDisplay());
        $this->assertStringContainsString('my_workflow', $tester->getDisplay());
    }

    public function testCommandDeletesExistingStateAndReportsSuccess()
    {
        $store = new WorkflowStateStore();
        $store->save(new WorkflowState('state-to-delete'));

        $command = new DeleteWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', 'id' => 'state-to-delete']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($store->has('state-to-delete'));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('state-to-delete', $display);
        $this->assertStringContainsString('my_workflow', $display);
    }

    public function testCommandDeleteInvokesDeleteOnStore()
    {
        $store = $this->createMock(WorkflowStateStoreInterface::class);
        $store->method('has')->with('state-42')->willReturn(true);
        $store->expects($this->once())->method('delete')->with('state-42');

        $command = new DeleteWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', 'id' => 'state-42']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testCommandUnwrapsTraceableWorkflowStateStore()
    {
        $inner = new WorkflowStateStore();
        $inner->save(new WorkflowState('state-99'));
        $traceable = new TraceableWorkflowStateStore($inner);

        $command = new DeleteWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $traceable,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', 'id' => 'state-99']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($inner->has('state-99'));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('state-99', $display);
    }

    public function testCommandWrapsDeleteExceptionInRuntimeException()
    {
        $store = $this->createMock(WorkflowStateStoreInterface::class);
        $store->method('has')->willReturn(true);
        $store->method('delete')->willThrowException(new \Exception('backend failure'));

        $command = new DeleteWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('backend failure');

        $tester->execute(['workflow' => 'my_workflow', 'id' => 'some-id']);
    }

    public function testCommandWorksWithMultipleWorkflowsRegistered()
    {
        $storeA = new WorkflowStateStore();
        $storeA->save(new WorkflowState('state-wf-a'));
        $storeB = new WorkflowStateStore();
        $storeB->save(new WorkflowState('state-wf-b'));

        $command = new DeleteWorkflowCommand(new ServiceLocator([
            'workflow_a' => static fn (): object => $storeA,
            'workflow_b' => static fn (): object => $storeB,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'workflow_a', 'id' => 'state-wf-a']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($storeA->has('state-wf-a'));
        // The sibling workflow store must be untouched
        $this->assertTrue($storeB->has('state-wf-b'));
    }

    public function testCommandUnwrapsTraceableWrappingNonListableManagedStore()
    {
        $innerStore = new class implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface {
            /** @var array<string, WorkflowStateInterface> */
            private array $data = [];

            public function setup(): void
            {
            }

            public function drop(): void
            {
                $this->data = [];
            }

            public function save(WorkflowStateInterface $state): void
            {
                $this->data[$state->getId()] = $state;
            }

            public function load(string $id): WorkflowStateInterface
            {
                if (!isset($this->data[$id])) {
                    throw new \LogicException('Not found: '.$id);
                }

                return $this->data[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->data[$id]);
            }

            public function delete(string $id): void
            {
                unset($this->data[$id]);
            }
        };

        $innerStore->save(new WorkflowState('state-managed'));
        $traceable = new TraceableWorkflowStateStore($innerStore);

        $command = new DeleteWorkflowCommand(new ServiceLocator([
            'managed_workflow' => static fn (): object => $traceable,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'managed_workflow', 'id' => 'state-managed']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse($innerStore->has('state-managed'));
    }
}

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
use Symfony\AI\Agent\Workflow\Command\ListWorkflowCommand;
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
final class ListWorkflowCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new ListWorkflowCommand(new ServiceLocator([]));

        $this->assertSame('ai:workflow:list', $command->getName());
        $this->assertSame('List the persisted states of a workflow', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('workflow'));

        $argument = $definition->getArgument('workflow');
        $this->assertSame('Name of the workflow whose persisted states to list', $argument->getDescription());
        $this->assertTrue($argument->isRequired());
    }

    public function testCommandThrowsWhenNoWorkflowIsConfigured()
    {
        $command = new ListWorkflowCommand(new ServiceLocator([]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No workflow is configured.');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandThrowsWhenWorkflowNameIsUnknown()
    {
        $command = new ListWorkflowCommand(new ServiceLocator([
            'other' => static fn (): object => new WorkflowStateStore(),
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "unknown" workflow does not exist, use "other".');

        $tester->execute(['workflow' => 'unknown']);
    }

    public function testCommandThrowsWhenStoreDoesNotSupportListing()
    {
        $store = $this->createMock(WorkflowStateStoreInterface::class);

        $command = new ListWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The state store of the "my_workflow" workflow does not support listing.');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandPrintsInformationalMessageWhenNoStatesPersisted()
    {
        $store = new WorkflowStateStore();

        $command = new ListWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'No state is currently persisted for the "my_workflow" workflow.',
            $tester->getDisplay(),
        );
    }

    public function testCommandPrintsPersistedStateIdsInTable()
    {
        $store = new WorkflowStateStore();
        $store->save(new WorkflowState('state-id-1'));
        $store->save(new WorkflowState('state-id-2'));

        $command = new ListWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('state-id-1', $display);
        $this->assertStringContainsString('state-id-2', $display);
        $this->assertStringContainsString('State ID', $display);
    }

    public function testCommandUnwrapsTraceableStore()
    {
        $inner = new WorkflowStateStore();
        $inner->save(new WorkflowState('state-id-1'));
        $traceable = new TraceableWorkflowStateStore($inner);

        $command = new ListWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $traceable,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('state-id-1', $tester->getDisplay());
    }

    public function testCommandThrowsWhenTraceableWrapsNonListableStore()
    {
        $nonListableInner = new class implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface {
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

        $traceable = new TraceableWorkflowStateStore($nonListableInner);

        $command = new ListWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $traceable,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The state store of the "my_workflow" workflow does not support listing.');

        $tester->execute(['workflow' => 'my_workflow']);
    }
}

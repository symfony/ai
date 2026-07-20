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
use Symfony\AI\Agent\Workflow\Command\DropWorkflowCommand;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\TraceableWorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DropWorkflowCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new DropWorkflowCommand(new ServiceLocator([]));

        $this->assertSame('ai:workflow:drop', $command->getName());
        $this->assertSame('Drop the infrastructure and every persisted state of a workflow', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('workflow'));
        $this->assertTrue($definition->hasOption('force'));

        $argument = $definition->getArgument('workflow');
        $this->assertSame('Name of the workflow whose state store to drop', $argument->getDescription());
        $this->assertTrue($argument->isRequired());

        $forceOption = $definition->getOption('force');
        $this->assertSame('Force dropping the state store and every state it holds', $forceOption->getDescription());
        $this->assertFalse($forceOption->acceptValue());
    }

    public function testCommandThrowsWhenNoWorkflowIsConfigured()
    {
        $command = new DropWorkflowCommand(new ServiceLocator([]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No workflow is configured to be dropped.');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandThrowsWhenWorkflowNameIsUnknown()
    {
        $command = new DropWorkflowCommand(new ServiceLocator([
            'other' => static fn (): object => new WorkflowStateStore(),
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "unknown" workflow does not exist, use "other".');

        $tester->execute(['workflow' => 'unknown']);
    }

    public function testCommandThrowsWhenStoreDoesNotSupportDrop()
    {
        $store = $this->createMock(WorkflowStateStoreInterface::class);

        $command = new DropWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The state store of the "my_workflow" workflow does not support to be dropped.');

        $tester->execute(['workflow' => 'my_workflow', '--force' => true]);
    }

    public function testCommandWarnsAndFailsWithoutForceOption()
    {
        $store = $this->createMock(ManagedWorkflowStateStoreInterface::class);
        $store->expects($this->never())->method('drop');

        $command = new DropWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('The --force option is required to drop the workflow state store.', $tester->getDisplay());
    }

    public function testCommandInvokesDrop()
    {
        $store = $this->createMock(ManagedWorkflowStateStoreInterface::class);
        $store->expects($this->once())->method('drop');

        $command = new DropWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'The state store of the "my_workflow" workflow was dropped successfully.',
            $tester->getDisplay(),
        );
    }

    public function testCommandWrapsDropExceptionInRuntimeException()
    {
        $store = $this->createMock(ManagedWorkflowStateStoreInterface::class);
        $store->expects($this->once())->method('drop')->willThrowException(new \Exception('backend error'));

        $command = new DropWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while dropping the state store of the "my_workflow" workflow: backend error');

        $tester->execute(['workflow' => 'my_workflow', '--force' => true]);
    }

    public function testCommandUnwrapsTraceableStore()
    {
        $inner = new WorkflowStateStore();
        $traceable = new TraceableWorkflowStateStore($inner);

        $command = new DropWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $traceable,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'The state store of the "my_workflow" workflow was dropped successfully.',
            $tester->getDisplay(),
        );
    }
}

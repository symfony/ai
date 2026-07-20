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
use Symfony\AI\Agent\Workflow\Command\SetupWorkflowCommand;
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
final class SetupWorkflowCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new SetupWorkflowCommand(new ServiceLocator([]));

        $this->assertSame('ai:workflow:setup', $command->getName());
        $this->assertSame('Prepare the required infrastructure for a workflow state store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('workflow'));

        $argument = $definition->getArgument('workflow');
        $this->assertSame('Name of the workflow whose state store to set up', $argument->getDescription());
        $this->assertTrue($argument->isRequired());
    }

    public function testCommandThrowsWhenNoWorkflowIsConfigured()
    {
        $command = new SetupWorkflowCommand(new ServiceLocator([]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No workflow is configured to be set up.');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandThrowsWhenWorkflowNameIsUnknown()
    {
        $command = new SetupWorkflowCommand(new ServiceLocator([
            'other' => static fn (): object => new WorkflowStateStore(),
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "unknown" workflow does not exist, use "other".');

        $tester->execute(['workflow' => 'unknown']);
    }

    public function testCommandThrowsWhenStoreDoesNotSupportSetup()
    {
        $store = $this->createMock(WorkflowStateStoreInterface::class);

        $command = new SetupWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The state store of the "my_workflow" workflow does not support setup.');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandInvokesSetupAndReportsSuccess()
    {
        $store = $this->createMock(ManagedWorkflowStateStoreInterface::class);
        $store->expects($this->once())->method('setup');

        $command = new SetupWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'The state store of the "my_workflow" workflow was set up successfully.',
            $tester->getDisplay(),
        );
    }

    public function testCommandWrapsStoreExceptionInRuntimeException()
    {
        $store = $this->createMock(ManagedWorkflowStateStoreInterface::class);
        $store->expects($this->once())->method('setup')->willThrowException(new \Exception('backend error'));

        $command = new SetupWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $store,
        ]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while setting up the state store of the "my_workflow" workflow: backend error');

        $tester->execute(['workflow' => 'my_workflow']);
    }

    public function testCommandUnwrapsTraceableStore()
    {
        $inner = new WorkflowStateStore();
        $traceable = new TraceableWorkflowStateStore($inner);

        $command = new SetupWorkflowCommand(new ServiceLocator([
            'my_workflow' => static fn (): object => $traceable,
        ]));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['workflow' => 'my_workflow']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'The state store of the "my_workflow" workflow was set up successfully.',
            $tester->getDisplay(),
        );
    }
}

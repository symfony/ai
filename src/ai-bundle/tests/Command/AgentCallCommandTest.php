<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */

namespace Symfony\AI\AiBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\AiBundle\Command\AgentCallCommand;
use Symfony\AI\AiBundle\Exception\RuntimeException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AgentCallCommandTest extends TestCase
{
    public function testCommandFailsWithInvalidAgent()
    {
        $agent = $this->createMock(AgentInterface::class);

        $agents = [
            'test' => $agent,
        ];

        $command = new AgentCallCommand($this->createServiceLocator($agents));
        $application = self::createApplication($command);

        $commandTester = new CommandTester($command);

        try {
            $commandTester->execute([
                'agent' => 'invalid',
            ]);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Agent "invalid" not found.', $e->getMessage());
            $this->assertStringContainsString('Available agents: "test"', $e->getMessage());
        }
    }

    public function testCommandExecutesWithValidAgent()
    {
        $agent = new MockAgent([
            'Hello' => 'Hello! How can I help you today?',
            'Thanks' => static fn (MessageBag $messages): string => \sprintf('You sent %d messages.', \count($messages)),
        ]);

        $agents = [
            'test' => $agent,
        ];

        $command = new AgentCallCommand($this->createServiceLocator($agents));
        $application = self::createApplication($command);

        $commandTester = new CommandTester($command);

        // Simulate user input
        $commandTester->setInputs(['Hello', 'Thanks', 'exit']);

        $commandTester->execute([
            'agent' => 'test',
        ]);

        $output = $commandTester->getDisplay();

        // Check for expected output (without system prompt since we're not using real agent with processors)
        $this->assertStringContainsString('Chat with test Agent', $output);
        $this->assertStringContainsString('Type your message and press Enter', $output);
        $this->assertStringContainsString('Assistant', $output);
        $this->assertStringContainsString('Hello! How can I help you today?', $output);
        $this->assertStringContainsString('You sent 3 messages.', $output);
        $this->assertStringNotContainsString('Error', $output);
        $this->assertStringContainsString('Goodbye!', $output);
    }

    public function testCommandHandlesQuitCommand()
    {
        $agent = $this->createMock(AgentInterface::class);
        $agents = [
            'test' => $agent,
        ];

        $command = new AgentCallCommand($this->createServiceLocator($agents));
        $application = self::createApplication($command);

        $commandTester = new CommandTester($command);

        // Test with 'quit' command
        $commandTester->setInputs(['quit']);

        $commandTester->execute([
            'agent' => 'test',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Goodbye!', $output);
    }

    public function testCommandHandlesAgentCallException()
    {
        $agent = $this->createMock(AgentInterface::class);

        $agent->expects($this->once())
            ->method('call')
            ->willThrowException(new RuntimeException('API error'));

        $agents = [
            'test' => $agent,
        ];

        $command = new AgentCallCommand($this->createServiceLocator($agents));
        $application = self::createApplication($command);

        $commandTester = new CommandTester($command);

        // Simulate user input
        $commandTester->setInputs(['Hello', 'exit']);

        $commandTester->execute([
            'agent' => 'test',
        ]);

        $output = $commandTester->getDisplay();

        // Check that error is displayed
        $this->assertStringContainsString('Error: API error', $output);
    }

    public function testInitializeMethodSelectsCorrectAgent()
    {
        $agent1 = $this->createMock(AgentInterface::class);
        $agent2 = $this->createMock(AgentInterface::class);
        $result = new TextResult('Response from agent 2');

        // Only agent2 should be called
        $agent1->expects($this->never())
            ->method('call');

        $agent2->expects($this->once())
            ->method('call')
            ->willReturn($this->execution($result));

        $agents = [
            'first' => $agent1,
            'second' => $agent2,
        ];

        $command = new AgentCallCommand($this->createServiceLocator($agents));
        $application = self::createApplication($command);

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['test', 'exit']);

        // Execute with 'second' agent - initialize should select agent2
        $commandTester->execute([
            'agent' => 'second',
        ]);

        $output = $commandTester->getDisplay();

        // Verify correct agent was used
        $this->assertStringContainsString('Chat with second Agent', $output);
        $this->assertStringContainsString('Response from agent 2', $output);
    }

    /**
     * @param array<string, AgentInterface> $agents
     *
     * @return ServiceLocator<AgentInterface>
     */
    private function createServiceLocator(array $agents): ServiceLocator
    {
        $factories = [];
        foreach ($agents as $serviceId => $agent) {
            $factories[$serviceId] = static fn () => $agent;
        }

        return new ServiceLocator($factories);
    }

    private static function createApplication(Command $command): Application
    {
        $application = new Application();
        $application->addCommands([$command]);

        return $application;
    }

    private function execution(ResultInterface $result): Execution
    {
        return new Execution(static function () use ($result): \Generator {
            yield new ResultUpdate($result);
        });
    }
}

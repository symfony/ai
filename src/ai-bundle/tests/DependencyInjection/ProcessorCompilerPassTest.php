<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\AiBundle\DependencyInjection\ProcessorCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ProcessorCompilerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('agent1', Agent::class)
            ->addTag('ai.agent');
        $container
            ->register('agent2', Agent::class)
            ->addTag('ai.agent');
        $container
            ->register(DummyInputProcessor1::class, DummyInputProcessor1::class)
            ->addTag('ai.agent.input_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyInputProcessor2::class, DummyInputProcessor2::class)
            ->addTag('ai.agent.input_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyInputProcessor3::class, DummyInputProcessor3::class)
            ->addTag('ai.agent.input_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyOutputProcessor1::class, DummyOutputProcessor1::class)
            ->addTag('ai.agent.output_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyOutputProcessor2::class, DummyOutputProcessor2::class)
            ->addTag('ai.agent.input_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyOutputProcessor3::class, DummyOutputProcessor3::class)
            ->addTag('ai.agent.input_processor', ['tagged_by' => 'interface']);
        $container
            ->register(DummyInputProcessor1::class, DummyInputProcessor1::class)
            ->addTag('ai.agent.input_processor', ['agent' => 'agent1', 'priority' => -100]);
        $container
            ->register(DummyInputProcessor2::class, DummyInputProcessor2::class)
            ->addTag('ai.agent.input_processor', ['agent' => 'agent2']);
        $container
            ->register(DummyInputProcessor3::class, DummyInputProcessor2::class)
            ->addTag('ai.agent.input_processor', ['priority' => 100]);
        $container
            ->register(DummyOutputProcessor1::class, DummyOutputProcessor1::class)
            ->addTag('ai.agent.output_processor', ['agent' => 'agent1', 'priority' => -100]);
        $container
            ->register(DummyOutputProcessor2::class, DummyOutputProcessor2::class)
            ->addTag('ai.agent.output_processor', ['agent' => 'agent2']);
        $container
            ->register(DummyOutputProcessor3::class, DummyOutputProcessor3::class)
            ->addTag('ai.agent.output_processor', ['priority' => 100]);

        (new ProcessorCompilerPass())->process($container);

        $this->assertEquals(
            [
                new Reference(DummyInputProcessor3::class),
                new Reference(DummyInputProcessor1::class),
            ],
            $container->getDefinition('agent1')->getArgument(2)
        );
        $this->assertEquals(
            [
                new Reference(DummyOutputProcessor3::class),
                new Reference(DummyOutputProcessor1::class),
            ],
            $container->getDefinition('agent1')->getArgument(3)
        );
        $this->assertEquals(
            [
                new Reference(DummyInputProcessor3::class),
                new Reference(DummyInputProcessor2::class),
            ],
            $container->getDefinition('agent2')->getArgument(2)
        );
        $this->assertEquals(
            [
                new Reference(DummyOutputProcessor3::class),
                new Reference(DummyOutputProcessor2::class),
            ],
            $container->getDefinition('agent2')->getArgument(3)
        );
    }

    public function testProcessSkipsMultiAgent()
    {
        $container = new ContainerBuilder();

        // Regular Agent service - should be processed
        $container
            ->register('agent1', Agent::class)
            ->setArguments([null, null, [], []])
            ->addTag('ai.agent');

        // MultiAgent service - should NOT be processed
        $orchestratorRef = new Reference('orchestrator');
        $handoffs = [new Definition('Symfony\AI\Agent\MultiAgent\Handoff')];
        $fallbackRef = new Reference('fallback');
        $name = 'support';

        $container
            ->register('multi_agent', MultiAgent::class)
            ->setArguments([$orchestratorRef, $handoffs, $fallbackRef, $name])
            ->addTag('ai.agent');

        // Add processors
        $container
            ->register(DummyInputProcessor1::class, DummyInputProcessor1::class)
            ->addTag('ai.agent.input_processor');
        $container
            ->register(DummyOutputProcessor1::class, DummyOutputProcessor1::class)
            ->addTag('ai.agent.output_processor');

        (new ProcessorCompilerPass())->process($container);

        // Regular agent should have processors injected
        $this->assertEquals(
            [new Reference(DummyInputProcessor1::class)],
            $container->getDefinition('agent1')->getArgument(2)
        );
        $this->assertEquals(
            [new Reference(DummyOutputProcessor1::class)],
            $container->getDefinition('agent1')->getArgument(3)
        );

        // MultiAgent arguments should remain unchanged
        $multiAgentDef = $container->getDefinition('multi_agent');
        $this->assertInstanceOf(Reference::class, $multiAgentDef->getArgument(0));
        $this->assertIsArray($multiAgentDef->getArgument(1));
        $this->assertInstanceOf(Reference::class, $multiAgentDef->getArgument(2));
        $this->assertSame('support', $multiAgentDef->getArgument(3));
    }
}

class DummyInputProcessor1 implements InputProcessorInterface
{
    public function processInput(Input $input): void
    {
    }
}
class DummyInputProcessor2 implements InputProcessorInterface
{
    public function processInput(Input $input): void
    {
    }
}
class DummyInputProcessor3 implements InputProcessorInterface
{
    public function processInput(Input $input): void
    {
    }
}
class DummyOutputProcessor1 implements OutputProcessorInterface
{
    public function processOutput(Output $output): void
    {
    }
}
class DummyOutputProcessor2 implements OutputProcessorInterface
{
    public function processOutput(Output $output): void
    {
    }
}
class DummyOutputProcessor3 implements OutputProcessorInterface
{
    public function processOutput(Output $output): void
    {
    }
}

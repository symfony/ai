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
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\MultiAgent\Handoff;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
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

        // tagged through the interface: applies to every agent
        $container
            ->register(DummyContextProcessor1::class, DummyContextProcessor1::class)
            ->addTag('ai.agent.context_processor', ['tagged_by' => 'interface']);

        // tagged for a single agent, ordered by priority
        $container
            ->register(DummyContextProcessor2::class, DummyContextProcessor2::class)
            ->addTag('ai.agent.context_processor', ['agent' => 'agent1', 'priority' => -100]);
        $container
            ->register(DummyContextProcessor3::class, DummyContextProcessor3::class)
            ->addTag('ai.agent.context_processor', ['agent' => 'agent1', 'priority' => 100]);

        (new ProcessorCompilerPass())->process($container);

        $this->assertEquals([
            new Reference(DummyContextProcessor3::class),
            new Reference(DummyContextProcessor1::class),
            new Reference(DummyContextProcessor2::class),
        ], $container->getDefinition('agent1')->getArgument('$contextProcessors'));

        $this->assertEquals([
            new Reference(DummyContextProcessor1::class),
        ], $container->getDefinition('agent2')->getArgument('$contextProcessors'));
    }

    public function testProcessSkipsMultiAgent()
    {
        $container = new ContainerBuilder();

        $container
            ->register('agent1', Agent::class)
            ->setArguments([null, null, []])
            ->addTag('ai.agent');

        $container
            ->register('multi_agent', MultiAgent::class)
            ->setArguments([new Reference('orchestrator'), [new Definition(Handoff::class)], new Reference('fallback'), 'support'])
            ->addTag('ai.agent');

        $container
            ->register(DummyContextProcessor1::class, DummyContextProcessor1::class)
            ->addTag('ai.agent.context_processor');

        (new ProcessorCompilerPass())->process($container);

        $this->assertEquals(
            [new Reference(DummyContextProcessor1::class)],
            $container->getDefinition('agent1')->getArgument('$contextProcessors')
        );

        // the MultiAgent has a different constructor signature and stays untouched
        $multiAgentDef = $container->getDefinition('multi_agent');
        $this->assertInstanceOf(Reference::class, $multiAgentDef->getArgument(0));
        $this->assertIsArray($multiAgentDef->getArgument(1));
        $this->assertInstanceOf(Reference::class, $multiAgentDef->getArgument(2));
        $this->assertSame('support', $multiAgentDef->getArgument(3));
    }
}

class DummyContextProcessor1 implements ContextProcessorInterface
{
    public static function supportedTypes(): array
    {
        return [];
    }

    public function process(AgentRequest $request, AgentContext $context): void
    {
    }
}

class DummyContextProcessor2 extends DummyContextProcessor1
{
}

class DummyContextProcessor3 extends DummyContextProcessor1
{
}

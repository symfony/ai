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
use Symfony\AI\Agent\Workflow\AgentWorkflow;
use Symfony\AI\AiBundle\DependencyInjection\WorkflowGuardCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class WorkflowGuardCompilerPassTest extends TestCase
{
    public function testGuardTaggedForWorkflowIsAttached()
    {
        $container = new ContainerBuilder();
        $this->registerWorkflow($container, 'content_pipeline');
        $container->register('app.quality_guard', \stdClass::class)
            ->addTag('ai.agent_workflow.guard', ['workflow' => 'content_pipeline', 'priority' => 0]);

        (new WorkflowGuardCompilerPass())->process($container);

        $guards = $container->getDefinition('ai.workflow.content_pipeline')->getArgument(4);
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(Reference::class, $guards[0]);
        $this->assertSame('app.quality_guard', (string) $guards[0]);
    }

    public function testGuardWithoutWorkflowIsAttachedToEveryWorkflow()
    {
        $container = new ContainerBuilder();
        $this->registerWorkflow($container, 'content_pipeline');
        $this->registerWorkflow($container, 'review_pipeline');
        $container->register('app.global_guard', \stdClass::class)
            ->addTag('ai.agent_workflow.guard', ['workflow' => null, 'priority' => 0]);

        (new WorkflowGuardCompilerPass())->process($container);

        $this->assertCount(1, $container->getDefinition('ai.workflow.content_pipeline')->getArgument(4));
        $this->assertCount(1, $container->getDefinition('ai.workflow.review_pipeline')->getArgument(4));
    }

    public function testGuardForAnotherWorkflowIsNotAttached()
    {
        $container = new ContainerBuilder();
        $this->registerWorkflow($container, 'content_pipeline');
        $container->register('app.other_guard', \stdClass::class)
            ->addTag('ai.agent_workflow.guard', ['workflow' => 'review_pipeline', 'priority' => 0]);

        (new WorkflowGuardCompilerPass())->process($container);

        $this->assertSame([], $container->getDefinition('ai.workflow.content_pipeline')->getArgument(4));
    }

    public function testGuardsAreOrderedByDescendingPriority()
    {
        $container = new ContainerBuilder();
        $this->registerWorkflow($container, 'content_pipeline');
        $container->register('app.low_guard', \stdClass::class)
            ->addTag('ai.agent_workflow.guard', ['workflow' => 'content_pipeline', 'priority' => 0]);
        $container->register('app.high_guard', \stdClass::class)
            ->addTag('ai.agent_workflow.guard', ['workflow' => 'content_pipeline', 'priority' => 100]);

        (new WorkflowGuardCompilerPass())->process($container);

        $guards = $container->getDefinition('ai.workflow.content_pipeline')->getArgument(4);
        $this->assertSame('app.high_guard', (string) $guards[0]);
        $this->assertSame('app.low_guard', (string) $guards[1]);
    }

    public function testConfiguredGuardsArePreservedAndAppendedTo()
    {
        $container = new ContainerBuilder();
        $this->registerWorkflow($container, 'content_pipeline', [new Reference('app.configured_guard')]);
        $container->register('app.attribute_guard', \stdClass::class)
            ->addTag('ai.agent_workflow.guard', ['workflow' => 'content_pipeline', 'priority' => 0]);

        (new WorkflowGuardCompilerPass())->process($container);

        $guards = $container->getDefinition('ai.workflow.content_pipeline')->getArgument(4);
        $this->assertCount(2, $guards);
        $this->assertSame('app.configured_guard', (string) $guards[0]);
        $this->assertSame('app.attribute_guard', (string) $guards[1]);
    }

    public function testHighPriorityAttributeGuardIsOrderedBeforeConfiguredGuards()
    {
        $container = new ContainerBuilder();
        $this->registerWorkflow($container, 'content_pipeline', [new Reference('app.configured_guard')]);
        $container->register('app.high_attribute_guard', \stdClass::class)
            ->addTag('ai.agent_workflow.guard', ['workflow' => 'content_pipeline', 'priority' => 100]);

        (new WorkflowGuardCompilerPass())->process($container);

        $guards = $container->getDefinition('ai.workflow.content_pipeline')->getArgument(4);
        $this->assertCount(2, $guards);
        $this->assertSame('app.high_attribute_guard', (string) $guards[0]);
        $this->assertSame('app.configured_guard', (string) $guards[1]);
    }

    public function testRepeatedAttributeAttachesGuardToEachNamedWorkflow()
    {
        $container = new ContainerBuilder();
        $this->registerWorkflow($container, 'content_pipeline');
        $this->registerWorkflow($container, 'review_pipeline');
        $container->register('app.shared_guard', \stdClass::class)
            ->addTag('ai.agent_workflow.guard', ['workflow' => 'content_pipeline', 'priority' => 0])
            ->addTag('ai.agent_workflow.guard', ['workflow' => 'review_pipeline', 'priority' => 0]);

        (new WorkflowGuardCompilerPass())->process($container);

        $this->assertCount(1, $container->getDefinition('ai.workflow.content_pipeline')->getArgument(4));
        $this->assertCount(1, $container->getDefinition('ai.workflow.review_pipeline')->getArgument(4));
    }

    public function testWithoutTaggedGuardsTheWorkflowIsUntouched()
    {
        $container = new ContainerBuilder();
        $this->registerWorkflow($container, 'content_pipeline');

        (new WorkflowGuardCompilerPass())->process($container);

        $this->assertSame([], $container->getDefinition('ai.workflow.content_pipeline')->getArgument(4));
    }

    /**
     * @param list<Reference> $configuredGuards
     */
    private function registerWorkflow(ContainerBuilder $container, string $name, array $configuredGuards = []): void
    {
        // Only argument 4 (the guards list) is relevant to the compiler pass.
        $container->register('ai.workflow.'.$name, AgentWorkflow::class)
            ->setArgument(4, $configuredGuards)
            ->addTag('ai.agent_workflow', ['name' => $name]);
    }
}

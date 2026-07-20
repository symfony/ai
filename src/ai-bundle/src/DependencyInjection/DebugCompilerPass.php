<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\DependencyInjection;

use Symfony\AI\Agent\Toolbox\TraceableToolbox;
use Symfony\AI\Agent\TraceableAgent;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\TraceableAgentWorkflow;
use Symfony\AI\Agent\Workflow\TraceableWorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
use Symfony\AI\Chat\TraceableChat;
use Symfony\AI\Chat\TraceableMessageStore;
use Symfony\AI\Platform\TraceablePlatform;
use Symfony\AI\Store\TraceableStore;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\String\u;

final class DebugCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('kernel.debug')) {
            return;
        }

        foreach (array_keys($container->findTaggedServiceIds('ai.platform')) as $platform) {
            $traceablePlatformDefinition = (new Definition(TraceablePlatform::class))
                ->setDecoratedService($platform, priority: -1024)
                ->setArguments([new Reference('.inner')])
                ->addTag('ai.traceable_platform')
                ->addTag('kernel.reset', ['method' => 'reset']);
            $suffix = u($platform)->after('ai.platform.')->toString();
            $container->setDefinition('ai.traceable_platform.'.$suffix, $traceablePlatformDefinition);
        }

        foreach (array_keys($container->findTaggedServiceIds('ai.message_store')) as $messageStore) {
            $traceableMessageStoreDefinition = (new Definition(TraceableMessageStore::class))
                ->setDecoratedService($messageStore, priority: -1024)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(ClockInterface::class),
                ])
                ->addTag('ai.traceable_message_store')
                ->addTag('kernel.reset', ['method' => 'reset']);
            $suffix = u($messageStore)->afterLast('.')->toString();
            $container->setDefinition('ai.traceable_message_store.'.$suffix, $traceableMessageStoreDefinition);
        }

        foreach (array_keys($container->findTaggedServiceIds('ai.chat')) as $chat) {
            $traceableChatDefinition = (new Definition(TraceableChat::class))
                ->setDecoratedService($chat, priority: -1024)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(ClockInterface::class),
                ])
                ->addTag('ai.traceable_chat')
                ->addTag('kernel.reset', ['method' => 'reset']);
            $suffix = u($chat)->afterLast('.')->toString();
            $container->setDefinition('ai.traceable_chat.'.$suffix, $traceableChatDefinition);
        }

        foreach (array_keys($container->findTaggedServiceIds('ai.toolbox')) as $toolbox) {
            $traceableToolboxDefinition = (new Definition(TraceableToolbox::class))
                ->setDecoratedService($toolbox, priority: -1024)
                ->setArguments([new Reference('.inner')])
                ->addTag('ai.traceable_toolbox')
                ->addTag('kernel.reset', ['method' => 'reset']);
            $suffix = u($toolbox)->afterLast('.')->toString();
            $container->setDefinition('ai.traceable_toolbox.'.$suffix, $traceableToolboxDefinition);
        }

        foreach (array_keys($container->findTaggedServiceIds('ai.agent')) as $agent) {
            $traceableAgentDefinition = (new Definition(TraceableAgent::class))
                ->setDecoratedService($agent, priority: -1024)
                ->setArguments([new Reference('.inner')])
                ->addTag('ai.traceable_agent')
                ->addTag('kernel.reset', ['method' => 'reset']);
            $suffix = u($agent)->afterLast('.')->toString();
            $container->setDefinition('ai.traceable_agent.'.$suffix, $traceableAgentDefinition);
        }

        foreach (array_keys($container->findTaggedServiceIds('ai.store')) as $store) {
            $traceableStoreDefinition = (new Definition(TraceableStore::class))
                ->setDecoratedService($store, priority: -1024)
                ->setArguments([new Reference('.inner')])
                ->addTag('ai.traceable_store')
                ->addTag('kernel.reset', ['method' => 'reset']);
            $suffix = u($store)->afterLast('.')->toString();
            $container->setDefinition('ai.traceable_store.'.$suffix, $traceableStoreDefinition);
        }

        foreach (array_keys($container->findTaggedServiceIds('ai.agent_workflow')) as $agentWorkflow) {
            $traceableAgentWorkflowDefinition = (new Definition(TraceableAgentWorkflow::class))
                ->setDecoratedService($agentWorkflow, priority: -1024)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(ClockInterface::class),
                ])
                ->addTag('ai.traceable_agent_workflow')
                ->addTag('kernel.reset', ['method' => 'reset']);
            $suffix = u($agentWorkflow)->afterLast('.')->toString();
            $container->setDefinition('ai.traceable_agent_workflow.'.$suffix, $traceableAgentWorkflowDefinition);
        }

        foreach (array_keys($container->findTaggedServiceIds('ai.agent_workflow.state_store')) as $workflowStateStore) {
            if (!$this->supportsWorkflowStateStoreTracing($container, $workflowStateStore)) {
                continue;
            }

            $traceableWorkflowStateStoreDefinition = (new Definition(TraceableWorkflowStateStore::class))
                ->setDecoratedService($workflowStateStore, priority: -1024)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(ClockInterface::class),
                ])
                ->addTag('ai.traceable_agent_workflow_state_store')
                ->addTag('kernel.reset', ['method' => 'reset']);
            $suffix = u($workflowStateStore)->afterLast('.')->toString();
            $container->setDefinition('ai.traceable_agent_workflow_state_store.'.$suffix, $traceableWorkflowStateStoreDefinition);
        }
    }

    /**
     * The traceable decorator requires a store that is both readable/writable and manageable; custom
     * stores implementing fewer interfaces are left untraced rather than breaking the container build.
     */
    private function supportsWorkflowStateStoreTracing(ContainerBuilder $container, string $serviceId): bool
    {
        $class = $container->findDefinition($serviceId)->getClass() ?? (class_exists($serviceId) ? $serviceId : null);

        if (null === $class) {
            return false;
        }

        if (!class_exists($class) && !interface_exists($class)) {
            return false;
        }

        return is_a($class, WorkflowStateStoreInterface::class, true)
            && is_a($class, ManagedWorkflowStateStoreInterface::class, true);
    }
}

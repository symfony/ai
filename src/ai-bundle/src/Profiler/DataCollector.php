<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-import-type PlatformCallData from TraceablePlatform
 * @phpstan-import-type MessageStoreData from TraceableMessageStore
 * @phpstan-import-type ChatData from TraceableChat
 * @phpstan-import-type AgentData from TraceableAgent
 * @phpstan-import-type PolicyHandlerData from TraceablePolicyHandler
 *
 * @phpstan-type CollectedPlatformCallData array{
 *     model: string,
 *     input: array<mixed>|string|object,
 *     options: array<string, mixed>,
 *     result: string|iterable<mixed>|object|null,
 *     metadata: Metadata,
 * }
 */
final class DataCollector extends AbstractDataCollector implements LateDataCollectorInterface
{
    /**
     * @var TraceablePlatform[]
     */
    private readonly array $platforms;

    /**
     * @var TraceableToolbox[]
     */
    private readonly array $toolboxes;

    /**
     * @var TraceableMessageStore[]
     */
    private readonly array $messageStores;

    /**
     * @var TraceableChat[]
     */
    private readonly array $chats;

    /**
     * @var TraceableAgent[]
     */
    private readonly iterable $agents;

    /**
     * @var TraceablePolicyHandler[]
     */
    private readonly iterable $policyHandlers;

    /**
     * @param TraceablePlatform[]      $platforms
     * @param TraceableToolbox[]       $toolboxes
     * @param TraceableMessageStore[]  $messageStores
     * @param TraceableChat[]          $chats
     * @param TraceableAgent[]         $agents
     * @param TraceablePolicyHandler[] $policyHandlers
     */
    public function __construct(
        iterable $platforms,
        iterable $toolboxes,
        iterable $messageStores,
        iterable $chats,
        iterable $agents,
        iterable $policyHandlers,
    ) {
        $this->platforms = $platforms instanceof \Traversable ? iterator_to_array($platforms) : $platforms;
        $this->toolboxes = $toolboxes instanceof \Traversable ? iterator_to_array($toolboxes) : $toolboxes;
        $this->messageStores = $messageStores instanceof \Traversable ? iterator_to_array($messageStores) : $messageStores;
        $this->chats = $chats instanceof \Traversable ? iterator_to_array($chats) : $chats;
        $this->agents = $agents instanceof \Traversable ? iterator_to_array($agents) : $agents;
        $this->policyHandlers = $policyHandlers instanceof \Traversable ? iterator_to_array($policyHandlers) : $policyHandlers;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->lateCollect();
    }

    public function lateCollect(): void
    {
        $this->data = [
            'tools' => $this->getAllTools(),
            'platform_calls' => array_merge(...array_map($this->awaitCallResults(...), $this->platforms)),
            'tool_calls' => array_merge(...array_map(static fn (TraceableToolbox $toolbox): array => $toolbox->calls, $this->toolboxes)),
            'messages' => array_merge(...array_map(static fn (TraceableMessageStore $messageStore): array => $messageStore->calls, $this->messageStores)),
            'chats' => array_merge(...array_map(static fn (TraceableChat $chat): array => $chat->calls, $this->chats)),
            'agents' => array_merge(...array_map(static fn (TraceableAgent $agent): array => $agent->calls, $this->agents)),
            'policy_handlers' => array_merge(...array_map(static fn (TraceablePolicyHandler $policyHandler): array => $policyHandler->calls, $this->policyHandlers)),
        ];
    }

    public static function getTemplate(): string
    {
        return '@Ai/data_collector.html.twig';
    }

    /**
     * @return CollectedPlatformCallData[]
     */
    public function getPlatformCalls(): array
    {
        return $this->data['platform_calls'] ?? [];
    }

    /**
     * @return Tool[]
     */
    public function getTools(): array
    {
        return $this->data['tools'] ?? [];
    }

    /**
     * @return ToolResult[]
     */
    public function getToolCalls(): array
    {
        return $this->data['tool_calls'] ?? [];
    }

    /**
     * @return MessageStoreData[]
     */
    public function getMessages(): array
    {
        return $this->data['messages'] ?? [];
    }

    /**
     * @return ChatData[]
     */
    public function getChats(): array
    {
        return $this->data['chats'] ?? [];
    }

    /**
     * @return AgentData[]
     */
    public function getAgents(): array
    {
        return $this->data['agents'] ?? [];
    }

    /**
     * @return PolicyHandlerData[]
     */
    public function getPolicyHandlers(): array
    {
        return $this->data['policy_handlers'] ?? [];
    }

    /**
     * @return Tool[]
     */
    private function getAllTools(): array
    {
        return array_merge(...array_map(static fn (TraceableToolbox $toolbox) => $toolbox->getTools(), $this->toolboxes));
    }

    /**
     * @return CollectedPlatformCallData[]
     */
    private function awaitCallResults(TraceablePlatform $platform): array
    {
        $calls = $platform->calls;
        foreach ($calls as $key => $call) {
            $result = $call['result']->getResult();

            if (isset($platform->resultCache[$result])) {
                $call['result'] = $platform->resultCache[$result];
            } else {
                $content = $result->getContent();
                $call['result'] = $content instanceof \Generator ? null : $content;
            }

            $call['metadata'] = $result->getMetadata();

            $calls[$key] = $call;
        }

        return $calls;
    }
}

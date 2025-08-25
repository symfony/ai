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

use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Agent\ChatInterface;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-import-type PlatformCallData from TraceablePlatform
 * @phpstan-import-type ToolCallData from TraceableToolbox
 * @phpstan-import-type MessageStoreData from TraceableMessageStore
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
     * @var MessageStoreInterface[]
     */
    private readonly array $messageStores;

    /**
     * @var ChatInterface[]
     */
    private readonly array $chats;

    /**
     * @param TraceablePlatform[]     $platforms
     * @param TraceableToolbox[]      $toolboxes
     * @param TraceableMessageStore[] $messageStores
     * @param TraceableChat[]         $chats
     */
    public function __construct(
        iterable $platforms,
        private readonly ToolboxInterface $defaultToolBox,
        iterable $toolboxes,
        iterable $messageStores,
        iterable $chats,
    ) {
        $this->platforms = $platforms instanceof \Traversable ? iterator_to_array($platforms) : $platforms;
        $this->toolboxes = $toolboxes instanceof \Traversable ? iterator_to_array($toolboxes) : $toolboxes;
        $this->messageStores = $messageStores instanceof \Traversable ? iterator_to_array($messageStores) : $messageStores;
        $this->chats = $chats instanceof \Traversable ? iterator_to_array($chats) : $chats;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->lateCollect();
    }

    public function lateCollect(): void
    {
        $this->data = [
            'tools' => $this->defaultToolBox->getTools(),
            'platform_calls' => array_merge(...array_map($this->awaitCallResults(...), $this->platforms)),
            'tool_calls' => array_merge(...array_map(static fn (TraceableToolbox $toolbox): array => $toolbox->calls, $this->toolboxes)),
            'message_stores' => array_merge(...array_map(static fn (TraceableMessageStore $messageStore): array => $messageStore->messages, $this->messageStores)),
            'chats_ids' => array_map(static fn (TraceableChat $chat): string => $chat->getId(), $this->chats),
        ];
    }

    public static function getTemplate(): string
    {
        return '@Ai/data_collector.html.twig';
    }

    /**
     * @return PlatformCallData[]
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
     * @return ToolCallData[]
     */
    public function getToolCalls(): array
    {
        return $this->data['tool_calls'] ?? [];
    }

    /**
     * @return MessageStoreData[]
     */
    public function getMessageStores(): array
    {
        return $this->data['message_stores'] ?? [];
    }

    /**
     * @return string[]
     */
    public function getChatsIds(): array
    {
        return $this->data['chats_ids'] ?? [];
    }

    /**
     * @return array{
     *     model: Model,
     *     input: array<mixed>|string|object,
     *     options: array<string, mixed>,
     *     result: string|iterable<mixed>|object|null
     * }[]
     */
    private function awaitCallResults(TraceablePlatform $platform): array
    {
        $calls = $platform->calls;
        foreach ($calls as $key => $call) {
            $result = $call['result']->await();

            if (isset($platform->resultCache[$result])) {
                $call['result'] = $platform->resultCache[$result];
            } else {
                $call['result'] = $result->getContent();
            }

            $calls[$key] = $call;
        }

        return $calls;
    }
}

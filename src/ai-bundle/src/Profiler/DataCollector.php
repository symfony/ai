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

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-import-type PlatformCallData from TraceablePlatform
 * @phpstan-import-type ToolCallData from TraceableToolbox
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
     * @var list<array{method: string, duration: float, input: mixed, result: mixed, error: ?\Throwable}>
     */
    private array $collectedChatCalls = [];

    /**
     * @param iterable<TraceablePlatform> $platforms
     * @param iterable<TraceableToolbox>  $toolboxes
     */
    public function __construct(
        iterable $platforms,
        private readonly ToolboxInterface $defaultToolBox,
        iterable $toolboxes,
    ) {
        $this->platforms = $platforms instanceof \Traversable ? iterator_to_array($platforms) : $platforms;
        $this->toolboxes = $toolboxes instanceof \Traversable ? iterator_to_array($toolboxes) : $toolboxes;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $this->data = [
            'tools' => $this->defaultToolBox->getTools(),
            'platform_calls' => array_merge(...array_map($this->awaitCallResults(...), $this->platforms)),
            'tool_calls' => array_merge(...array_map(fn (TraceableToolbox $toolbox) => $toolbox->calls, $this->toolboxes)),
            'chat_calls' => $this->cloneVar($this->collectedChatCalls),
        ];
    }

    public function collectChatCall(string $method, float $duration, mixed $input, mixed $result, ?\Throwable $error): void
    {
        $this->collectedChatCalls[] = [
            'method' => $method,
            'duration' => $duration,
            'input' => $input,
            'result' => $result,
            'error' => $error,
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
     * @return list<array{method: string, duration: float, input: mixed, result: mixed, error: ?\Throwable}>
     */
    public function getChatCalls(): array
    {
        if (!isset($this->data['chat_calls'])) {
            return [];
        }

        $chatCalls = $this->data['chat_calls']->getValue(true);

        /** @var list<array{method: string, duration: float, input: mixed, result: mixed, error: ?\Throwable}> $chatCalls */
        return $chatCalls;
    }

    public function reset(): void
    {
        $this->data = [];
        $this->collectedChatCalls = [];
    }

    /**
     * @return array{
     * model: Model,
     * input: array<mixed>|string|object,
     * options: array<string, mixed>,
     * result: string|iterable<mixed>|object|null
     * }[]
     */
    private function awaitCallResults(TraceablePlatform $platform): array
    {
        $calls = $platform->calls;
        foreach ($calls as $key => $call) {
            $result = $call['result'];

            if (isset($platform->resultCache[$result])) {
                $call['result'] = $platform->resultCache[$result];
            } else {
                $call['result'] = $result->asText();
            }

            $calls[$key] = $call;
        }

        return $calls;
    }
}

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
    private array $collectedCalls = [];

    /**
     * @param TraceablePlatform[] $platforms
     * @param TraceableToolbox[]  $toolboxes
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
        $platformCalls = [];
        foreach ($this->platforms as $platform) {
            $calls = $platform->calls;
            foreach ($calls as $call) {
                $result = $call['result']->await();
                if (isset($platform->resultCache[$result])) {
                    $call['result'] = $platform->resultCache[$result];
                } else {
                    $call['result'] = $result->getContent();
                }

                $call['model'] = $this->cloneVar($call['model']);
                $call['input'] = $this->cloneVar($call['input']);
                $call['options'] = $this->cloneVar($call['options']);
                $call['result'] = $this->cloneVar($call['result']);

                $platformCalls[] = $call;
            }
        }

        $toolCalls = [];
        foreach ($this->toolboxes as $toolbox) {
            foreach ($toolbox->calls as $call) {
                $call['call'] = $this->cloneVar($call['call']);
                $call['result'] = $this->cloneVar($call['result']);
                $toolCalls[] = $call;
            }
        }

        $this->data = [
            'tools' => $this->defaultToolBox->getTools(),
            'platform_calls' => $platformCalls,
            'tool_calls' => $toolCalls,
            'agent_calls' => $this->collectedCalls,
        ];
    }

    public function collectAgentCall(string $method, float $duration, mixed $input, mixed $result, ?\Throwable $error): void
    {
        $this->collectedCalls[] = [
            'method' => $method,
            'duration' => $duration,
            'input' => $this->cloneVar($input),
            'result' => $this->cloneVar($result),
            'error' => $this->cloneVar($error),
        ];
    }

    public static function getTemplate(): string
    {
        return '@Ai/data_collector.html.twig';
    }

    /**
     * @return array{
     *     model: Data,
     *     input: Data,
     *     options: Data,
     *     result: Data
     * }[]
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
    public function getAgentCalls(): array
    {
        return $this->data['agent_calls'] ?? [];
    }

    public function reset(): void
    {
        $this->data = [];
        $this->collectedCalls = [];
    }
}

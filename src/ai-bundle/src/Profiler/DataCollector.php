<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AIBundle\Profiler;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-import-type PlatformCallData from TraceablePlatform
 * @phpstan-import-type ToolCallData from TraceableToolbox
 */
final class DataCollector extends AbstractDataCollector
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
     * @param TraceablePlatform[] $platforms
     * @param TraceableToolbox[]  $toolboxes
     */
    public function __construct(
        #[TaggedIterator('symfony_ai.traceable_platform')]
        iterable $platforms,
        private readonly ToolboxInterface $defaultToolBox,
        #[TaggedIterator('symfony_ai.traceable_toolbox')]
        iterable $toolboxes,
    ) {
        $this->platforms = $platforms instanceof \Traversable ? iterator_to_array($platforms) : $platforms;
        $this->toolboxes = $toolboxes instanceof \Traversable ? iterator_to_array($toolboxes) : $toolboxes;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'tools' => $this->defaultToolBox->getTools(),
            'platform_calls' => array_merge(...array_map(fn (TraceablePlatform $platform) => $platform->calls, $this->platforms)),
            'tool_calls' => array_merge(...array_map(fn (TraceableToolbox $toolbox) => $toolbox->calls, $this->toolboxes)),
        ];
    }

    public static function getTemplate(): string
    {
        return '@AI/data_collector.html.twig';
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
}

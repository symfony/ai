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
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-type ToolCallData array{
 *     call: ToolCall,
 *     result: string,
 * }
 */
final class TraceableToolbox implements ToolboxInterface
{
    /**
     * @var ToolCallData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    public function getTools(): array
    {
        return $this->toolbox->getTools();
    }

    public function execute(ToolCall $toolCall): mixed
    {
        $result = $this->toolbox->execute($toolCall);

        $this->calls[] = [
            'call' => $toolCall,
            'result' => $result,
        ];

        return $result;
    }
}

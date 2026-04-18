<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TraceableToolbox implements ToolboxInterface, ResetInterface
{
    /**
     * @var ToolResult[]
     */
    private array $calls = [];

    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    public function getTools(): array
    {
        return $this->toolbox->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        return $this->calls[] = $this->toolbox->execute($toolCall);
    }

    /**
     * @return ToolResult[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}

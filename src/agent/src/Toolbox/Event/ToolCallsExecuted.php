<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Event;

use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallsExecuted
{
    /**
     * @var ToolResult[]
     */
    private readonly array $toolResults;
    private ResultInterface $result;

    public function __construct(ToolResult ...$toolResults)
    {
        $this->toolResults = $toolResults;
    }

    public function hasResult(): bool
    {
        return isset($this->result);
    }

    public function setResult(ResultInterface $result): void
    {
        $this->result = $result;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    /**
     * @return ToolResult[]
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }
}

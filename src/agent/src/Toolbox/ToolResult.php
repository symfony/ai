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

use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolResult
{
    /**
     * @param Source[] $sources
     */
    public function __construct(
        private readonly ToolCall $toolCall,
        private readonly mixed $result,
        private readonly array $sources = [],
    ) {
    }

    public function getToolCall(): ToolCall
    {
        return $this->toolCall;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * @return Source[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }
}

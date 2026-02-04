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

use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallRequested implements StoppableEventInterface
{
    private bool $denied = false;
    private ?string $denialReason = null;
    private ?ToolResult $result = null;

    public function __construct(
        private readonly ToolCall $toolCall,
        private readonly Tool $metadata,
    ) {
    }

    public function getToolCall(): ToolCall
    {
        return $this->toolCall;
    }

    public function getMetadata(): Tool
    {
        return $this->metadata;
    }

    /**
     * Deny the tool execution with a reason.
     */
    public function deny(string $reason): void
    {
        $this->denied = true;
        $this->denialReason = $reason;
    }

    public function isDenied(): bool
    {
        return $this->denied;
    }

    public function getDenialReason(): ?string
    {
        return $this->denialReason;
    }

    /**
     * Set a custom result to skip the actual tool execution.
     */
    public function setResult(ToolResult $result): void
    {
        $this->result = $result;
    }

    public function hasResult(): bool
    {
        return null !== $this->result;
    }

    public function getResult(): ?ToolResult
    {
        return $this->result;
    }

    public function isPropagationStopped(): bool
    {
        return $this->denied || null !== $this->result;
    }
}

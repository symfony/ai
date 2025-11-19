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

use Symfony\AI\Platform\Tool\Tool;

/**
 * Dispatched after successfully invoking a tool.
 */
final class ToolCallFailed
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly object $tool,
        private readonly Tool $metadata,
        private readonly array $arguments,
        private readonly \Throwable $exception,
    ) {
    }

    public function getTool(): object
    {
        return $this->tool;
    }

    public function getMetadata(): Tool
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}

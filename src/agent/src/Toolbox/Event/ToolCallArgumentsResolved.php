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
 * Dispatched after the arguments are denormalized, just before invoking the tool.
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ToolCallArgumentsResolved
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly object $tool,
        private readonly Tool $metadata,
        private readonly array $arguments,
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
}

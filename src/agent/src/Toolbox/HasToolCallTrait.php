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

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
trait HasToolCallTrait
{
    private ?ToolCall $toolCall = null;

    /** @var \WeakMap<object, ToolCall>|null */
    private ?\WeakMap $fiberToolCalls = null;

    public function setToolCall(?ToolCall $toolCall): void
    {
        if (null === $fiber = \Fiber::getCurrent()) {
            $this->toolCall = $toolCall;

            return;
        }

        if (null === $toolCall) {
            if (null !== $this->fiberToolCalls) {
                unset($this->fiberToolCalls[$fiber]);
            }

            return;
        }

        $this->fiberToolCalls ??= new \WeakMap();
        $this->fiberToolCalls[$fiber] = $toolCall;
    }

    public function getToolCall(): ?ToolCall
    {
        if (null !== $fiber = \Fiber::getCurrent()) {
            return $this->fiberToolCalls[$fiber] ?? null;
        }

        return $this->toolCall;
    }
}

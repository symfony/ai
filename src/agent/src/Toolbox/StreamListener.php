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

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
use Symfony\AI\Platform\Result\Stream\StartEvent;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamListener extends AbstractStreamListener
{
    private string $buffer = '';
    private ?ResultInterface $result = null;
    private bool $toolHandled = false;

    public function __construct(
        private readonly \Closure $handleToolCallsCallback,
    ) {
    }

    public function onStart(StartEvent $event): void
    {
        $this->buffer = '';
        $this->result = null;
        $this->toolHandled = false;
    }

    public function onDelta(DeltaEvent $event): void
    {
        // Skip further processing if a tool call has already been handled
        if ($this->toolHandled) {
            $event->skipDelta();

            return;
        }

        $delta = $event->getDelta();

        // Build up assistant message for tool call response.
        if ($delta instanceof TextDelta) {
            $this->buffer .= $delta->getText();
        }

        if (!$delta instanceof ToolCallComplete) {
            return;
        }

        $this->result = ($this->handleToolCallsCallback)(new ToolCallResult($delta->getToolCalls()), Message::ofAssistant($this->buffer));

        \assert(null !== $this->result);
        $content = $this->result->getContent();

        if (\is_string($content)) {
            $event->setDelta(new TextDelta($content));
        } elseif ($content instanceof DeltaInterface || $content instanceof \Generator) {
            $event->setDelta($content);
        }

        $this->toolHandled = true;
    }

    public function onComplete(CompleteEvent $event): void
    {
        $this->buffer = '';
        $this->toolHandled = false;

        if (null !== $this->result) {
            $event->getMetadata()->merge($this->result->getMetadata());
        }
    }
}

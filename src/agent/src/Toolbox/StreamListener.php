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
use Symfony\AI\Platform\Result\Stream\ChunkEvent;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
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
        $this->toolHandled = false;
    }

    public function onChunk(ChunkEvent $event): void
    {
        // Skip further processing if a tool call has already been handled
        if ($this->toolHandled) {
            $event->skipChunk();

            return;
        }

        $chunk = $event->getChunk();

        // Build up assistant message for tool call response.
        if (\is_string($chunk)) {
            $this->buffer .= $chunk;
        }

        if (!$chunk instanceof ToolCallResult) {
            return;
        }

        $this->result = ($this->handleToolCallsCallback)($chunk, Message::ofAssistant($this->buffer));
        $event->setChunk($this->result->getContent());

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

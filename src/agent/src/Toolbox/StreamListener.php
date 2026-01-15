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
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\ChunkEvent;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StreamListener extends AbstractStreamListener
{
    private string $buffer = '';
    private bool $toolHandled = false;

    public function __construct(
        private readonly \Closure $handleToolCallsCallback,
    ) {
    }

    public function onStreamStart(): void
    {
        $this->buffer = '';
        $this->toolHandled = false;
    }

    public function onChunk(ChunkEvent $event): void
    {
        // Skip further processing if a tool call has already been handled
        if ($this->toolHandled) {
            $event->skipChunk();
        }

        $chunk = $event->getStream()->current();

        // Build up assistant message for tool call response.
        if (\is_string($chunk)) {
            $this->buffer .= $chunk;
        }

        if (!$chunk instanceof ToolCallResult) {
            return;
        }

        $event->setChunk(
            ($this->handleToolCallsCallback)($chunk, Message::ofAssistant($this->buffer))->getContent()
        );

        $this->toolHandled = true;
    }

    public function onStreamComplete(): void
    {
        $this->buffer = '';
        $this->toolHandled = false;
    }
}

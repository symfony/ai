<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\ChunkEvent;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ChatStreamListener extends AbstractStreamListener
{
    private string $content = '';

    public function __construct(
        private readonly MessageBag $messages,
        private readonly MessageStoreInterface $store,
        private readonly ?string $identifier = null,
    ) {
    }

    public function onChunk(ChunkEvent $event): void
    {
        $chunk = $event->getChunk();

        if (\is_string($chunk)) {
            $this->content .= $chunk;
        }
    }

    public function onComplete(CompleteEvent $event): void
    {
        $assistantMessage = Message::ofAssistant($this->content);
        $assistantMessage->getMetadata()->merge($event->getResult()->getMetadata());

        $this->messages->add($assistantMessage);

        $this->store->save($this->messages, $this->identifier);
    }
}

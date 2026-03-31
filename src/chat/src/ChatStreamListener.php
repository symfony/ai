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
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ChatStreamListener extends AbstractStreamListener
{
    private string $content = '';

    public function __construct(
        private readonly MessageBag $messages,
        private readonly MessageStoreInterface $store,
    ) {
    }

    public function onDelta(DeltaEvent $event): void
    {
        $delta = $event->getDelta();

        if ($delta instanceof TextDelta) {
            $this->content .= $delta;
        }
    }

    public function onComplete(CompleteEvent $event): void
    {
        $assistantMessage = Message::ofAssistant($this->content);
        $assistantMessage->getMetadata()->merge($event->getResult()->getMetadata());

        $this->messages->add($assistantMessage);

        $this->store->save($this->messages);
    }
}

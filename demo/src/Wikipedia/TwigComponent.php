<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Wikipedia;

use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Agent\ChatInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('wikipedia')]
final class TwigComponent
{
    use DefaultActionTrait;

    public function __construct(
        #[Autowire(service: 'ai.chat.wikipedia')]
        private readonly ChatInterface $chat,
        #[Autowire(service: 'ai.message_store.cache.wikipedia')]
        private readonly MessageStoreInterface $messageStore,
    ) {
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->chat->getCurrentMessageBag()->getMessages();
    }

    #[LiveAction]
    public function submit(#[LiveArg] string $message): void
    {
        $this->chat->submit(Message::ofUser($message));
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->messageStore->clear();
    }
}

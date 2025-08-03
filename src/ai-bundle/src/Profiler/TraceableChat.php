<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\ChatInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class TraceableChat implements ChatInterface
{
    public function __construct(
        private ChatInterface $chat,
    ) {
    }

    public function initiate(MessageBag $messages, ?string $id = null): void
    {
        $this->chat->initiate($messages, $id);
    }

    public function submit(UserMessage $message, ?string $id = null): AssistantMessage
    {
        return $this->chat->submit($message, $id);
    }

    public function getCurrentMessageBag(): MessageBag
    {
        return $this->chat->getCurrentMessageBag();
    }

    public function getMessageBag(string $id): MessageBag
    {
        return $this->chat->getMessageBag($id);
    }

    public function getId(): string
    {
        return $this->chat->getId();
    }
}

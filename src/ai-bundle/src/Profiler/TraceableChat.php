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
use Symfony\AI\Platform\Message\MessageBagInterface;
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

    public function initiate(MessageBagInterface $messages, ?string $id = null): void
    {
        $this->chat->initiate($messages, $id);
    }

    public function submit(UserMessage $message): AssistantMessage
    {
        return $this->chat->submit($message);
    }

    public function getCurrentMessageBag(): MessageBagInterface
    {
        return $this->chat->getCurrentMessageBag();
    }

    public function getId(): string
    {
        return $this->chat->getId();
    }
}

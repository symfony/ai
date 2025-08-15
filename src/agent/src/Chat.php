<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Chat implements ChatInterface
{
    private AbstractUid&TimeBasedUidInterface $currentMessageBag;

    public function __construct(
        private AgentInterface $agent,
        private MessageStoreInterface $store,
    ) {
    }

    public function initiate(MessageBagInterface $messages): void
    {
        $this->store->clear();
        $this->store->save($messages);

        $this->currentMessageBag = $messages->getId();
    }

    public function submit(UserMessage $message): AssistantMessage
    {
        $messagesBag = $this->store->load($this->currentMessageBag);

        $messagesBag->add($message);
        $result = $this->agent->call($messagesBag);

        \assert($result instanceof TextResult);

        $assistantMessage = Message::ofAssistant($result->getContent());
        $messagesBag->add($assistantMessage);

        $this->store->save($messagesBag);

        return $assistantMessage;
    }
}

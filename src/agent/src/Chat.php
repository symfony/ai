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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Chat implements ChatInterface
{
    public function __construct(
        private AgentInterface $agent,
        private MessageStoreInterface $store,
    ) {
    }

    public function initiate(MessageBagInterface $messages): void
    {
        $messages->setSession($this->agent->getId());

        $this->store->clear($messages->getSession());
        $this->store->save($messages);
    }

    public function submit(UserMessage $message): AssistantMessage
    {
        $messagesBag = $this->store->load($this->agent->getId());

        $messagesBag->add($message);
        $result = $this->agent->call($messagesBag);

        \assert($result instanceof TextResult);

        $assistantMessage = Message::ofAssistant($result->getContent());
        $messagesBag->add($assistantMessage);

        $this->store->save($messagesBag);

        return $assistantMessage;
    }
}

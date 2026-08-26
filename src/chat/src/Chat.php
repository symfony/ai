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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Chat implements ChatInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly MessageStoreInterface&ManagedStoreInterface $store,
    ) {
    }

    public function initiate(MessageBag $messages): void
    {
        $this->store->drop();
        $this->store->save($messages);
    }

    public function submit(UserMessage $message): AssistantMessage
    {
        $messages = $this->store->load();

        $messages->add($message);

        // the execution is lazy, reading its result runs the agent and returns the underlying result
        $result = $this->agent->call($messages)->getResult();

        $assistantMessage = Message::ofAssistant($result);
        $assistantMessage->getMetadata()->merge($result->getMetadata());
        $messages->add($assistantMessage);

        $this->store->save($messages);

        return $assistantMessage;
    }

    public function stream(UserMessage $message): \Generator
    {
        $messages = $this->store->load();
        $messages->add($message);

        $execution = $this->agent->call($messages, ['stream' => true]);

        // the execution's deltas are the streamed answer; accumulate the text to persist it afterwards
        $content = '';
        foreach ($execution->asStream() as $delta) {
            if ($delta instanceof TextDelta) {
                $content .= $delta;
            }

            yield $delta;
        }

        $assistantMessage = Message::ofAssistant($content);
        $assistantMessage->getMetadata()->merge($execution->getMetadata());
        $messages->add($assistantMessage);

        $this->store->save($messages);
    }
}

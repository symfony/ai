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
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;

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
        $messages = $this->agent->call($this->store->load()->with($message))->getMessages();

        $this->store->save($messages);

        $answer = $messages->latestAs(Role::Assistant);
        \assert($answer instanceof AssistantMessage);

        return $answer;
    }

    public function stream(UserMessage $message): \Generator
    {
        $execution = $this->agent->call($this->store->load()->with($message), ['stream' => true]);

        yield from $execution->asStream();

        $this->store->save($execution->getMessages());
    }
}

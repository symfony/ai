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
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Toolbox\StreamResult as ToolboxStreamResult;
use Symfony\AI\Chat\Result\AccumulatingStreamResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

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

    public function submit(UserMessage $message): AssistantMessage|AccumulatingStreamResult
    {
        $messages = $this->store->load();

        $messages->add($message);
        $result = $this->agent->call($messages);

        if ($result instanceof StreamResult || $result instanceof ToolboxStreamResult) {
            if (!$this->store instanceof StreamableStoreInterface) {
                throw new RuntimeException($this->store::class.' does not support streaming.');
            }

            return new AccumulatingStreamResult($result, function (AssistantMessage $assistantMessage) use ($messages) {
                $messages->add($assistantMessage);
                $this->store->save($messages);
            });
        }

        \assert($result instanceof TextResult);

        $assistantMessage = Message::ofAssistant($result->getContent());

        $assistantMessage->getMetadata()->set($result->getMetadata()->all());

        $messages->add($assistantMessage);
        $this->store->save($messages);

        return $assistantMessage;
    }
}

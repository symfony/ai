<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Recipe;

use App\Conversation\ConversationIds;
use App\Recipe\Data\Recipe;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Chat
{
    private const CHAT = 'recipe';

    public function __construct(
        private readonly ConversationIds $conversationIds,
        #[Autowire(service: 'ai.message_store.cache.shared_conversations')]
        private readonly MessageStoreInterface $messageStore,
        #[Autowire(service: 'ai.agent.recipe')]
        private readonly AgentInterface $agent,
    ) {
    }

    public function getRecipe(): Recipe
    {
        $messages = $this->loadMessages()->getMessages();

        if (0 === \count($messages)) {
            throw new \RuntimeException('No recipe generated yet. Please submit a message first.');
        }

        $message = $messages[\count($messages) - 1];

        if (!$message->getMetadata()->has('recipe')) {
            throw new \RuntimeException('The last message does not contain a recipe.');
        }

        return $message->getMetadata()->get('recipe');
    }

    public function submitMessage(string $message): void
    {
        $this->messageStore->save($this->loadMessages()->with(Message::ofUser($message)));
    }

    /**
     * Whether the latest user message is still awaiting its recipe, i.e. a stream
     * should be (or is being) consumed for it.
     */
    public function isAwaitingRecipe(): bool
    {
        return $this->loadMessages()->isLastMessageFrom(Role::User);
    }

    /**
     * Streams the recipe as it is generated, yielding a progressively populated Recipe
     * for each partial snapshot and storing the final recipe in the conversation.
     *
     * @return \Generator<int, Recipe, void, Recipe>
     */
    public function getRecipeStream(MessageBag $messages): \Generator
    {
        // Only generate a recipe when the latest message is still awaiting one. This guards
        // against reconnecting, duplicate or stale SSE connections (e.g. after a stream error
        // or a reset) that would otherwise call the model with no pending user message and
        // trigger a provider "input required" error.
        if (!$messages->isLastMessageFrom(Role::User)) {
            return new Recipe();
        }

        $execution = $this->agent->call($messages, [
            'stream' => true,
            'response_format' => Recipe::class,
        ]);

        $recipe = new Recipe();
        foreach ($execution->asStreamedObject() as $partial) {
            \assert($partial instanceof Recipe);
            $recipe = $partial;

            yield $recipe;
        }

        // the agent stored the recipe as JSON, replacing it under its ID keeps the conversation readable
        $messages = $execution->getMessages();
        $answerId = $messages->latestAs(Role::Assistant)->getId();
        $assistantMessage = Message::ofAssistant($recipe->toString())->withId($answerId);
        $assistantMessage->getMetadata()->add('recipe', $recipe);

        $this->messageStore->save($messages->replace($answerId, $assistantMessage));

        return $recipe;
    }

    public function reset(): void
    {
        $this->messageStore->delete($this->conversationIds->get(self::CHAT));
        $this->conversationIds->reset(self::CHAT);
    }

    public function loadMessages(): MessageBag
    {
        return $this->messageStore->load($this->conversationIds->get(self::CHAT));
    }
}

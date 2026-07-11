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

use App\Recipe\Data\Recipe;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const CACHE_PREFIX = 'recipe-chat-';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CacheItemPoolInterface $cache,
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
        $messages = $this->loadMessages();

        $messages->add(Message::ofUser($message));

        $this->saveMessages($messages);
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
     * for each partial snapshot and persisting the final recipe to the chat store.
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

        $stream = $this->agent->call($messages, [
            'stream' => true,
            'response_format' => Recipe::class,
        ])->getContent();

        \assert(is_iterable($stream));

        $recipe = new Recipe();
        foreach ($stream as $delta) {
            if ($delta instanceof PartialObjectDelta) {
                $recipe = $delta->getObject();
                \assert($recipe instanceof Recipe);

                yield $recipe;
            }
        }

        $assistantMessage = Message::ofAssistant($recipe->toString());
        $assistantMessage->getMetadata()->add('recipe', $recipe);
        $messages->add($assistantMessage);

        $this->saveMessages($messages);

        return $recipe;
    }

    public function reset(): void
    {
        $this->cache->deleteItem($this->cacheKey());
    }

    public function loadMessages(): MessageBag
    {
        $item = $this->cache->getItem($this->cacheKey());

        return $item->isHit() ? $item->get() : new MessageBag();
    }

    private function saveMessages(MessageBag $messages): void
    {
        $item = $this->cache->getItem($this->cacheKey());
        $item->set($messages)->expiresAfter(self::CACHE_TTL);

        $this->cache->save($item);
    }

    private function cacheKey(): string
    {
        return self::CACHE_PREFIX.$this->requestStack->getSession()->getId();
    }
}

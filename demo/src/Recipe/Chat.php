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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const SESSION_KEY = 'recipe-chat';

    public function __construct(
        private readonly RequestStack $requestStack,
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
        $result = $this->agent->call($messages, ['response_format' => Recipe::class]);

        \assert($result instanceof ObjectResult);

        $recipe = $result->getContent();

        \assert($recipe instanceof Recipe);

        $assistantMessage = Message::ofAssistant($recipe->toString());
        $assistantMessage->getMetadata()->add('recipe', $result->getContent());
        $messages->add($assistantMessage);

        $this->saveMessages($messages);
    }

    public function reset(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    private function loadMessages(): MessageBag
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, new MessageBag());
    }

    private function saveMessages(MessageBag $messages): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $messages);
    }
}

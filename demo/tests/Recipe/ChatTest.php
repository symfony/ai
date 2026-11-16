<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Recipe;

use App\Conversation\ConversationIds;
use App\Recipe\Chat;
use App\Recipe\Data\Recipe;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class ChatTest extends TestCase
{
    public function testGetRecipeStreamSkipsModelCallWithoutPendingUserMessage()
    {
        $agent = new MockAgent();
        $chat = self::createChat($agent);

        $recipes = iterator_to_array($chat->getRecipeStream(new MessageBag()));

        // No user message to respond to (e.g. a reconnecting or stale SSE connection):
        // the model must not be called, otherwise the provider rejects the empty input.
        $this->assertSame([], $recipes);
        $agent->assertNotCalled();
    }

    public function testGetRecipeStreamSkipsModelCallWhenLatestMessageIsAssistant()
    {
        $agent = new MockAgent();
        $chat = self::createChat($agent);

        $messages = new MessageBag(
            Message::ofUser('A quick pasta'),
            Message::ofAssistant('Here is your recipe.'),
        );

        $recipes = iterator_to_array($chat->getRecipeStream($messages));

        $this->assertSame([], $recipes);
        $agent->assertNotCalled();
    }

    public function testGetRecipeStreamGeneratesRecipeForPendingUserMessage()
    {
        $firstPartial = new Recipe();
        $firstPartial->name = 'Pancakes';

        $secondPartial = new Recipe();
        $secondPartial->name = 'Pancakes';
        $secondPartial->duration = 20;

        $stream = new StreamResult((static function () use ($firstPartial, $secondPartial): \Generator {
            yield new PartialObjectDelta($firstPartial, '{"name":"Pancakes"}');
            yield new PartialObjectDelta($secondPartial, '{"name":"Pancakes","duration":20}');
        })());

        $agent = new MockAgent(['Give me pancakes' => $stream]);
        $chat = self::createChat($agent);

        $chat->submitMessage('Give me pancakes');
        $recipes = iterator_to_array($chat->getRecipeStream($chat->loadMessages()));

        $agent->assertCallCount(1);
        $this->assertSame(['stream' => true, 'response_format' => Recipe::class], $agent->getLastCall()['options']);

        $this->assertCount(2, $recipes);
        $this->assertSame('Pancakes', $recipes[1]->name);
        $this->assertSame(20, $recipes[1]->duration);

        $this->assertSame($recipes[1], $chat->getRecipe());
        $answer = $chat->loadMessages()->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $answer);
        $this->assertSame($recipes[1]->toString(), $answer->asText());
    }

    private static function createChat(MockAgent $agent): Chat
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        return new Chat(new ConversationIds($requestStack), new InMemoryMessageStore(), $agent);
    }
}

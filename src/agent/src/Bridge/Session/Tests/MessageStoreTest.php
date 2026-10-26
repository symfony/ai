<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Session\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Session\MessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    private Session $session;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $this->requestStack = new RequestStack([$request]);
    }

    public function testLoadReturnsAnEmptyBagWithTheGivenId()
    {
        $id = Uuid::v7();

        $messages = (new MessageStore($this->requestStack))->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testSavedConversationsAreLoadedByTheirId()
    {
        $store = new MessageStore($this->requestStack, 'chat');
        $first = new MessageBag(Message::ofUser('Hello'));
        $second = new MessageBag(Message::ofUser('Bonjour'));
        $continued = $first->with(Message::ofAssistant('Hi'));

        $store->save($first);
        $store->save($second);
        $store->save($continued);

        $this->assertEquals($continued, $store->load($first->getId()));
        $this->assertEquals($second, $store->load($second->getId()));
        $this->assertCount(2, $this->session->get('chat'));
    }

    public function testSavingDivergingMessagesConflicts()
    {
        $store = new MessageStore($this->requestStack);
        $messages = new MessageBag(Message::ofUser('Hello'));
        $store->save($messages->with(Message::ofAssistant('Hi')));

        $this->expectException(ConflictException::class);

        $store->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testDeleteRemovesOnlyTheGivenConversation()
    {
        $store = new MessageStore($this->requestStack);
        $first = new MessageBag(Message::ofUser('Hello'));
        $second = new MessageBag(Message::ofUser('Bonjour'));
        $store->save($first);
        $store->save($second);

        $store->delete($first->getId());

        $this->assertCount(0, $store->load($first->getId()));
        $this->assertCount(1, $store->load($second->getId()));
    }
}

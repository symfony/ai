<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Session\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Session\MessageStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class MessageStoreTest extends TestCase
{
    public function testSetupStoresEmptyMessageBag()
    {
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn(new Session(new MockArraySessionStorage()));

        $store = new MessageStore($requestStack, 'messages');
        $store->setup();

        $this->assertCount(0, $store->load());
    }

    public function testSetupWithCustomSessionKey()
    {
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn(new Session(new MockArraySessionStorage()));

        $store = new MessageStore($requestStack, 'custom_key');
        $store->setup();

        $this->assertCount(0, $store->load());
    }

    public function testSaveStoresMessageBag()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Test message'));

        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn(new Session(new MockArraySessionStorage()));

        $store = new MessageStore($requestStack, 'messages');
        $store->save($messageBag);

        $this->assertCount(1, $store->load());
    }

    public function testLoadReturnsStoredMessages()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Session message'));

        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn(new Session(new MockArraySessionStorage()));

        $store = new MessageStore($requestStack, 'messages');
        $store->save($messageBag);

        $result = $store->load();

        $this->assertSame($messageBag->getId()->toRfc4122(), $result->getId()->toRfc4122());
        $this->assertCount(1, $result);
    }

    public function testLoadReturnsEmptyMessageBagWhenNotSet()
    {
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn(new Session(new MockArraySessionStorage()));

        $store = new MessageStore($requestStack, 'messages');
        $result = $store->load();

        $this->assertInstanceOf(MessageBag::class, $result);
        $this->assertCount(0, $result);
    }

    public function testDropRemovesSessionKey()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Session message'));

        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn(new Session(new MockArraySessionStorage()));

        $store = new MessageStore($requestStack, 'messages');

        $store->save($messageBag);

        $this->assertCount(1, $store->load());

        $store->drop();

        $this->assertCount(0, $store->load());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests\Bridge\HttpFoundation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\HttpFoundation\SessionStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SessionStoreTest extends TestCase
{
    public function testSetupStoresEmptyMessageBag()
    {
        $session = $this->createMock(SessionInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn($session);

        $session->expects($this->once())
            ->method('set')
            ->with('messages', $this->isInstanceOf(MessageBag::class));

        $store = new SessionStore($requestStack, 'messages');
        $store->setup();
    }

    public function testSetupWithCustomSessionKey()
    {
        $session = $this->createMock(SessionInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn($session);

        $session->expects($this->once())
            ->method('set')
            ->with('custom_key', $this->isInstanceOf(MessageBag::class));

        $store = new SessionStore($requestStack, 'custom_key');
        $store->setup();
    }

    public function testSaveStoresMessageBag()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Test message'));

        $session = $this->createMock(SessionInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn($session);

        $session->expects($this->once())
            ->method('set')
            ->with('messages', $messageBag);

        $store = new SessionStore($requestStack, 'messages');
        $store->save($messageBag);
    }

    public function testLoadReturnsStoredMessages()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Session message'));

        $session = $this->createMock(SessionInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn($session);

        $session->expects($this->once())
            ->method('get')
            ->with('messages', $this->isInstanceOf(MessageBag::class))
            ->willReturn($messageBag);

        $store = new SessionStore($requestStack, 'messages');
        $result = $store->load();

        $this->assertSame($messageBag, $result);
        $this->assertCount(1, $result);
    }

    public function testLoadReturnsEmptyMessageBagWhenNotSet()
    {
        $session = $this->createMock(SessionInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn($session);

        $session->expects($this->once())
            ->method('get')
            ->with('messages', $this->isInstanceOf(MessageBag::class))
            ->willReturn(new MessageBag());

        $store = new SessionStore($requestStack, 'messages');
        $result = $store->load();

        $this->assertInstanceOf(MessageBag::class, $result);
        $this->assertCount(0, $result);
    }

    public function testDropRemovesSessionKey()
    {
        $session = $this->createMock(SessionInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getSession')
            ->willReturn($session);

        $session->expects($this->once())
            ->method('remove')
            ->with('messages');

        $store = new SessionStore($requestStack, 'messages');
        $store->drop();
    }
}

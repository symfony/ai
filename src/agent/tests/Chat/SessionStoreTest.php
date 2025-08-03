<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Chat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Chat\MessageStore\SessionStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(SessionStore::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Message::class)]
final class SessionStoreTest extends TestCase
{
    public function testItCanStore()
    {
        $storage = new MockArraySessionStorage();
        $storage->start();

        $request = Request::create('/');
        $request->setSession(new Session($storage));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Hello'));

        $store = new SessionStore($requestStack);
        $store->save($messageBag);

        $this->assertCount(1, $store->load('_message_store_session'));
    }

    public function testItCanStoreMultipleMessageBags()
    {
        $storage = new MockArraySessionStorage();
        $storage->start();

        $request = Request::create('/');
        $request->setSession(new Session($storage));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $firstMessageBag = new MessageBag();
        $firstMessageBag->add(Message::ofUser('Hello'));

        $secondMessageBag = new MessageBag();
        $secondMessageBag->add(Message::ofUser('Hello'));

        $store = new SessionStore($requestStack);
        $store->save($firstMessageBag, 'foo');
        $store->save($secondMessageBag, 'bar');

        $this->assertCount(1, $store->load('foo'));
        $this->assertCount(1, $store->load('bar'));
        $this->assertCount(0, $store->load('_message_store_session'));
    }

    public function testItCanClear()
    {
        $storage = new MockArraySessionStorage();
        $storage->start();

        $request = Request::create('/');
        $request->setSession(new Session($storage));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $bag = new MessageBag();
        $bag->add(Message::ofUser('Hello'));
        $bag->add(Message::ofUser('Hello'));

        $store = new SessionStore($requestStack);
        $store->save($bag);

        $this->assertCount(2, $store->load('_message_store_session'));

        $store->clear();

        $this->assertCount(0, $store->load('_message_store_session'));
    }
}

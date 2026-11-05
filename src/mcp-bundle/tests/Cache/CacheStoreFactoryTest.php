<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Cache;

use Mcp\Server\Session\Psr16SessionStore;
use Mcp\Server\Session\Psr6SessionStore;
use Mcp\Server\Subscription\Psr16NotificationBus;
use Mcp\Server\Subscription\Psr6NotificationBus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Cache\CacheStoreFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Uid\Uuid;

final class CacheStoreFactoryTest extends TestCase
{
    public function testSessionStoreUsesAPsr6PoolDirectly()
    {
        $pool = new ArrayAdapter();
        $store = CacheStoreFactory::sessionStore($pool, 'mcp-default-', 60);

        $this->assertInstanceOf(Psr6SessionStore::class, $store);

        $id = Uuid::v4();
        $this->assertTrue($store->write($id, 'data'));
        $this->assertTrue($pool->hasItem('mcp-default-'.$id));
    }

    public function testSessionStoreKeepsAcceptingAPsr16Cache()
    {
        $this->assertInstanceOf(Psr16SessionStore::class, CacheStoreFactory::sessionStore(new Psr16Cache(new ArrayAdapter()), 'mcp-default-', 60));
    }

    public function testNotificationBusUsesAPsr6PoolDirectly()
    {
        $pool = new ArrayAdapter();
        $bus = CacheStoreFactory::notificationBus($pool, 'mcp-default-notifications.', new NullLogger());

        $this->assertInstanceOf(Psr6NotificationBus::class, $bus);

        $this->assertSame(0, $bus->cursor());
        $pool->save($pool->getItem('mcp-default-notifications.cursor')->set(3));
        $this->assertSame(3, $bus->cursor());
    }

    public function testNotificationBusKeepsAcceptingAPsr16Cache()
    {
        $this->assertInstanceOf(Psr16NotificationBus::class, CacheStoreFactory::notificationBus(new Psr16Cache(new ArrayAdapter()), 'mcp-default-notifications.', new NullLogger()));
    }
}

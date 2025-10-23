<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests\Bridge\Redis;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Redis\MessageStore;
use Symfony\Component\Serializer\SerializerInterface;

final class MessageStoreTest extends TestCase
{
    public function testStoreCannotSetupOnExistingItem()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())->method('exists')->willReturn(true);

        $store = new MessageStore($redis, 'test', $serializer);
        $store->setup();
    }

    public function testStoreCanSetup()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->willReturn('');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::never())->method('exists');
        $redis->expects(self::once())->method('set');

        $store = new MessageStore($redis, 'test', $serializer);
        $store->setup();
    }

    public function testStoreCanDrop()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->willReturn('');

        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::never())->method('exists');
        $redis->expects(self::once())->method('set');

        $store = new MessageStore($redis, 'test', $serializer);
        $store->drop();
    }
}

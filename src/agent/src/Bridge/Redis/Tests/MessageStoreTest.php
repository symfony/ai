<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Redis\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Redis\MessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    public function testLoadReturnsAnEmptyBagWithTheGivenId()
    {
        $id = Uuid::v7();
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('get')->with('chat:'.$id->toRfc4122())->willReturn(false);

        $messages = (new MessageStore($redis, 'chat'))->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testLoadDeserializesTheStoredConversation()
    {
        $messages = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));
        $payload = $this->serializer()->serialize($messages, 'json');
        $redis = $this->createStub(\Redis::class);
        $redis->method('get')->willReturn($payload);

        $this->assertSame($payload, $this->serializer()->serialize((new MessageStore($redis, 'chat'))->load($messages->getId()), 'json'));
    }

    public function testLoadFailsWhenRedisReportsAnError()
    {
        $redis = $this->createStub(\Redis::class);
        $redis->method('get')->willReturn(false);
        $redis->method('getLastError')->willReturn('WRONGTYPE Operation against a key holding the wrong kind of value');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WRONGTYPE Operation against a key holding the wrong kind of value');

        (new MessageStore($redis, 'chat'))->load(Uuid::v7());
    }

    public function testSaveWritesTheConversationInAWatchedTransaction()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $key = 'chat:'.$messages->getId()->toRfc4122();
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('watch')->with($key);
        $redis->expects($this->once())->method('get')->with($key)->willReturn(false);
        $redis->expects($this->once())->method('multi')->willReturnSelf();
        $redis->expects($this->once())->method('set')->with($key, $this->serializer()->serialize($messages, 'json'))->willReturnSelf();
        $redis->expects($this->once())->method('exec')->willReturn([true]);
        $redis->expects($this->never())->method('unwatch');

        (new MessageStore($redis, 'chat'))->save($messages);
    }

    public function testSaveConflictsWhenTheMessagesDiverge()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willReturn($this->serializer()->serialize($messages->with(Message::ofAssistant('Hi')), 'json'));
        $redis->expects($this->once())->method('unwatch');
        $redis->expects($this->never())->method('multi');

        $this->expectException(ConflictException::class);

        (new MessageStore($redis, 'chat'))->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testSaveConflictsWhenTheKeyChangedDuringTheTransaction()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willReturn(false);
        $redis->method('multi')->willReturnSelf();
        $redis->method('set')->willReturnSelf();
        $redis->expects($this->once())->method('exec')->willReturn(false);
        $redis->expects($this->never())->method('unwatch');

        $this->expectException(ConflictException::class);

        (new MessageStore($redis, 'chat'))->save(new MessageBag(Message::ofUser('Hello')));
    }

    public function testSaveUnwatchesTheKeyWhenTheStoredConversationCannotBeLoaded()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willReturn('{invalid');
        $redis->expects($this->once())->method('unwatch');
        $redis->expects($this->never())->method('multi');

        $this->expectException(NotEncodableValueException::class);

        (new MessageStore($redis, 'chat'))->save(new MessageBag(Message::ofUser('Hello')));
    }

    public function testSaveUnwatchesTheKeyWhenRedisReportsAnError()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willReturn(false);
        $redis->method('getLastError')->willReturn('WRONGTYPE Operation against a key holding the wrong kind of value');
        $redis->expects($this->once())->method('unwatch');
        $redis->expects($this->never())->method('multi');

        $this->expectException(RuntimeException::class);

        (new MessageStore($redis, 'chat'))->save(new MessageBag(Message::ofUser('Hello')));
    }

    public function testDropDeletesAllConversationKeys()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('scan')->with(null, 'chat:'.str_repeat('?', 36))->willReturnCallback(static function (&$iterator): array {
            $iterator = 0;

            return ['chat:a', 'chat:b'];
        });
        $redis->expects($this->once())->method('del')->with(['chat:a', 'chat:b']);

        (new MessageStore($redis, 'chat'))->drop();
    }

    public function testDropMatchesThePrefixLiterally()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('scan')->with(null, 'chat\\[1\\]:'.str_repeat('?', 36))->willReturnCallback(static function (&$iterator): array {
            $iterator = 0;

            return [];
        });
        $redis->expects($this->never())->method('del');

        (new MessageStore($redis, 'chat[1]'))->drop();
    }

    public function testDropDeletesTheConversationKeysOfAPrefixedClient()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('getOption')->with(\Redis::OPT_PREFIX)->willReturn('app:');
        $redis->expects($this->once())->method('scan')->with(null, 'app:chat:'.str_repeat('?', 36))->willReturnCallback(static function (&$iterator): array {
            $iterator = 0;

            return ['app:chat:a', 'app:chat:b'];
        });
        $redis->expects($this->once())->method('del')->with(['chat:a', 'chat:b']);

        (new MessageStore($redis, 'chat'))->drop();
    }

    public function testSaveSerializesBeforeOpeningTheTransaction()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willThrowException(new NotNormalizableValueException('Not normalizable.'));
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->never())->method('watch');
        $redis->expects($this->never())->method('multi');

        $this->expectException(NotNormalizableValueException::class);

        (new MessageStore($redis, 'chat', $serializer))->save(new MessageBag(Message::ofUser('Hello')));
    }

    public function testDeleteRemovesTheConversationKey()
    {
        $id = Uuid::v7();
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('del')->with('chat:'.$id->toRfc4122());

        (new MessageStore($redis, 'chat'))->delete($id);
    }

    private function serializer(): Serializer
    {
        return new Serializer([new MessageBagNormalizer()], [new JsonEncoder()]);
    }
}

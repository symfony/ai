<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cache\CachePlatform;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Clock\MonotonicClock;

final class CachePlatformTest extends TestCase
{
    public function testPlatformCanReturnCachedResultWhenCalledTwice()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));

        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertTrue($secondDeferredResult->getMetadata()->has('cached_at'));
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformCanReturnCachedResultWhenCalledTwiceWithMessageBag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $adapter = new ArrayAdapter();

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter($adapter),
        );

        $messageBag = new MessageBag(
            Message::ofUser('Hello there'),
        );

        $deferredResult = $cachedPlatform->invoke('foo', $messageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertCount(3, $adapter->getValues());
         $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));
        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', $messageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertCount(3, $adapter->getValues());
        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertTrue($secondDeferredResult->getMetadata()->has('cached_at'));
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformReturnsCachedResultForSeparateMessageBagsWithSameContent()
    {
        // Two MessageBag instances with the same conversation must hit the
        // same cache entry — see #1245. The bag's UUID is per-instance state
        // and is intentionally excluded from the cache key.
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', new MessageBag(Message::ofUser('Hello there')), [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $deferredResult->getResult()->getContent());
        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));
        $cachedAt = $deferredResult->getMetadata()->get('cached_at');

        $secondDeferredResult = $cachedPlatform->invoke('foo', new MessageBag(Message::ofUser('Hello there')), [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertSame($cachedAt, $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformDistinguishesMessageBagsWithDifferentContent()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $cachedPlatform->invoke('foo', new MessageBag(Message::ofUser('Hello there')), [
            'prompt_cache_key' => 'symfony',
        ]);

        // Different user message → cache miss → second underlying invocation.
        $cachedPlatform->invoke('foo', new MessageBag(Message::ofUser('Hello world')), [
            'prompt_cache_key' => 'symfony',
        ]);
    }

    public function testLookupReturnsNullOnCacheMiss()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $this->assertNull($cachedPlatform->lookup('foo', new MessageBag(Message::ofUser('Hello there')), [
            'prompt_cache_key' => 'symfony',
        ]));
    }

    public function testLookupReturnsCachedResultOnCacheHitWithoutInvokingPlatform()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $cachedPlatform->invoke('foo', new MessageBag(Message::ofUser('Hello there')), [
            'prompt_cache_key' => 'symfony',
        ]);

        // A second `invoke()` here would also hit the cache, but `lookup()`
        // must not even attempt to invoke the underlying platform.
        $cached = $cachedPlatform->lookup('foo', new MessageBag(Message::ofUser('Hello there')), [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertNotNull($cached);
        $this->assertSame('test content', $cached->getResult()->getContent());
        $this->assertTrue($cached->getMetadata()->has('cached_at'));
    }

    public function testLookupReturnsNullWhenCacheKeyIsMissing()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $this->assertNull($cachedPlatform->lookup('foo', 'bar'));
        $this->assertNull($cachedPlatform->lookup('foo', 'bar', ['prompt_cache_key' => '']));
    }

    public function testPlatformCannotReturnCachedResultWhenCalledTwiceWhileUsingShortCustomTtl()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $clock = new MonotonicClock();

        $cachedPlatform = new CachePlatform(
            $platform,
            clock: $clock,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
            'prompt_cache_ttl' => 2,
        ]);

        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));

        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $clock->sleep(3);

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertTrue($secondDeferredResult->getMetadata()->has('cached_at'));
        $this->assertNotSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformCanReturnCachedResultWhenCalledTwiceWhileUsingShortCustomTtl()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $clock = new MonotonicClock();

        $cachedPlatform = new CachePlatform(
            $platform,
            clock: $clock,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
            'prompt_cache_ttl' => 5,
        ]);

        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));

        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $clock->sleep(2);

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertTrue($secondDeferredResult->getMetadata()->has('cached_at'));
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }
}

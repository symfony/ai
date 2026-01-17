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

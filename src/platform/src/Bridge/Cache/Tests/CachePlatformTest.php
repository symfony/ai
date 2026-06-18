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
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\String\UnicodeString;

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

        $userMessage = Message::ofUser('Hello there');
        $messageBag = new MessageBag($userMessage);

        $deferredResult = $cachedPlatform->invoke('foo', $messageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $deferredResult->getResult()->getContent());
        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));

        $secondDeferredResult = $cachedPlatform->invoke('foo', $messageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertTrue($secondDeferredResult->getMetadata()->has('cached_at'));
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
        $this->assertSame(
            (string) $deferredResult->getMetadata()->get('cache_key'),
            (string) $secondDeferredResult->getMetadata()->get('cache_key'),
        );
    }

    /**
     * Regression test for issue #2192: two MessageBags built from separately constructed
     * Message instances (each carrying a fresh random UUID) must still hit the cache because the
     * key is derived from the message content, not from the random identifiers.
     */
    public function testPlatformCanReturnCachedResultWhenCalledTwiceWithSeparateMessageBagsAndSeparateMessages()
    {
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

        $secondDeferredResult = $cachedPlatform->invoke('foo', new MessageBag(Message::ofUser('Hello there')), [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
        $this->assertSame(
            (string) $deferredResult->getMetadata()->get('cache_key'),
            (string) $secondDeferredResult->getMetadata()->get('cache_key'),
        );
    }

    public function testPlatformCannotReturnCachedResultWhenCalledTwiceWithUpdatedMessageBag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(
                new PlainConverter(new TextResult('First content')), new InMemoryRawResult(),
            ),
            new DeferredResult(
                new PlainConverter(new TextResult('Second content')), new InMemoryRawResult(),
            ),
        );

        $adapter = new ArrayAdapter();

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter($adapter),
        );

        $firstMessageBag = new MessageBag(Message::ofUser('Hello there'));

        $deferredResult = $cachedPlatform->invoke('foo', $firstMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('First content', $deferredResult->getResult()->getContent());
        $firstCacheKey = (string) $deferredResult->getMetadata()->get('cache_key');

        // Replaying the exact same content hits the cache, the inner platform is not called again.
        $secondDeferredResult = $cachedPlatform->invoke('foo', new MessageBag(Message::ofUser('Hello there')), [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('First content', $secondDeferredResult->getResult()->getContent());
        $this->assertSame($firstCacheKey, (string) $secondDeferredResult->getMetadata()->get('cache_key'));

        // A bag carrying a different user message produces a different key and therefore a miss.
        $secondMessageBag = new MessageBag(
            Message::ofUser('Hello there'),
            Message::ofAssistant('Second answer'),
            Message::ofUser('Follow up'),
        );

        $thirdDeferredResult = $cachedPlatform->invoke('foo', $secondMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('Second content', $thirdDeferredResult->getResult()->getContent());
        $this->assertNotSame($firstCacheKey, (string) $thirdDeferredResult->getMetadata()->get('cache_key'));

        // Replaying the second bag hits the cache and keeps the first entry cached.
        $fourthDeferredResult = $cachedPlatform->invoke('foo', $secondMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('Second content', $fourthDeferredResult->getResult()->getContent());
        $this->assertSame((string) $thirdDeferredResult->getMetadata()->get('cache_key'), (string) $fourthDeferredResult->getMetadata()->get('cache_key'));
        $this->assertArrayHasKey($firstCacheKey, $adapter->getValues());
        $this->assertArrayHasKey((string) $thirdDeferredResult->getMetadata()->get('cache_key'), $adapter->getValues());
    }

    public function testPlatformCachesWhenMessageBagHasNoUserMessage()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $messageBag = new MessageBag(Message::forSystem('Only a system message'));

        $deferredResult = $cachedPlatform->invoke('foo', $messageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', $messageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformCachesBareMessageInput()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $message = Message::ofUser('Hello there');

        $deferredResult = $cachedPlatform->invoke('foo', $message, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', Message::ofUser('Hello there'), [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformMissesWhenSystemPromptDiffers()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(
                new PlainConverter(new TextResult('First content')), new InMemoryRawResult(),
            ),
            new DeferredResult(
                new PlainConverter(new TextResult('Second content')), new InMemoryRawResult(),
            ),
        );

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $firstMessageBag = new MessageBag(
            Message::forSystem('You are a helpful assistant.'),
            Message::ofUser('Hello there'),
        );

        $deferredResult = $cachedPlatform->invoke('foo', $firstMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('First content', $deferredResult->getResult()->getContent());

        $secondMessageBag = new MessageBag(
            Message::forSystem('You are a strict assistant.'),
            Message::ofUser('Hello there'),
        );

        $secondDeferredResult = $cachedPlatform->invoke('foo', $secondMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('Second content', $secondDeferredResult->getResult()->getContent());
        $this->assertNotSame((string) $deferredResult->getMetadata()->get('cache_key'), (string) $secondDeferredResult->getMetadata()->get('cache_key'));
    }

    public function testPlatformMissesWhenAssistantTurnDiffers()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(
                new PlainConverter(new TextResult('First content')), new InMemoryRawResult(),
            ),
            new DeferredResult(
                new PlainConverter(new TextResult('Second content')), new InMemoryRawResult(),
            ),
        );

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $firstMessageBag = new MessageBag(
            Message::ofUser('Hello there'),
            Message::ofAssistant('First answer'),
            Message::ofUser('Follow up'),
        );

        $deferredResult = $cachedPlatform->invoke('foo', $firstMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('First content', $deferredResult->getResult()->getContent());

        $secondMessageBag = new MessageBag(
            Message::ofUser('Hello there'),
            Message::ofAssistant('Second answer'),
            Message::ofUser('Follow up'),
        );

        $secondDeferredResult = $cachedPlatform->invoke('foo', $secondMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('Second content', $secondDeferredResult->getResult()->getContent());
        $this->assertNotSame((string) $deferredResult->getMetadata()->get('cache_key'), (string) $secondDeferredResult->getMetadata()->get('cache_key'));
    }

    public function testPlatformCannotReturnCachedResultWhenCalledTwiceWhileUsingShortCustomTtl()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(
                new PlainConverter(new TextResult('First content')), new InMemoryRawResult(),
            ),
            new DeferredResult(
                new PlainConverter(new TextResult('Second content')), new InMemoryRawResult(),
            )
        );

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

        $this->assertSame('First content', $deferredResult->getResult()->getContent());

        $clock->sleep(3);

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('Second content', $secondDeferredResult->getResult()->getContent());
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

    /**
     * Regression test: a tool-call result must be cacheable with the default serializer shipped
     * with {@see CachePlatform} (which only registers the result normalizer), without relying on a
     * dedicated tool-call normalizer being present in the serializer.
     */
    public function testPlatformCachesToolCallResultWithDefaultSerializer()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new ToolCallResult([
                new ToolCall('call-1', 'get_weather', ['city' => 'Paris']),
            ])),
            new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $result = $deferredResult->getResult();
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertSame('call-1', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $toolCalls[0]->getArguments());

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $secondResult = $secondDeferredResult->getResult();
        $this->assertInstanceOf(ToolCallResult::class, $secondResult);
        $this->assertSame('call-1', $secondResult->getContent()[0]->getId());
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    /**
     * Streaming responses are not cacheable: the call must bypass the cache and delegate to the
     * inner platform on every request, even when a cache key is provided.
     */
    public function testPlatformBypassesCacheForStreamingResponses()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(new PlainConverter(new TextResult('streamed content')), new InMemoryRawResult()),
            new DeferredResult(new PlainConverter(new TextResult('streamed content')), new InMemoryRawResult()),
        );

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $firstDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
            'stream' => true,
        ]);

        $this->assertSame('streamed content', $firstDeferredResult->getResult()->getContent());
        $this->assertFalse($firstDeferredResult->getMetadata()->has('cached_at'));

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
            'stream' => true,
        ]);

        $this->assertSame('streamed content', $secondDeferredResult->getResult()->getContent());
        $this->assertFalse($secondDeferredResult->getMetadata()->has('cached_at'));
    }

    /**
     * A result that cannot be normalized into a cacheable representation (e.g. an unsupported
     * result type) must not break the request: the live result is returned instead of throwing,
     * and no cache metadata is attached.
     */
    public function testPlatformFailsOpenWhenResultCannotBeCached()
    {
        $uncacheableResult = $this->createMock(ResultInterface::class);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter($uncacheableResult),
            new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame($uncacheableResult, $deferredResult->getResult());
        $this->assertFalse($deferredResult->getMetadata()->has('cached_at'));
    }

    /**
     * A stale or corrupted cache entry (e.g. a payload shape from a previous version) must not
     * break the request: the entry is dropped and the inner platform is re-invoked instead of
     * throwing during denormalization.
     */
    public function testPlatformFailsOpenWhenCacheEntryIsCorrupted()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(new PlainConverter(new TextResult('First content')), new InMemoryRawResult()),
            new DeferredResult(new PlainConverter(new TextResult('Recovered content')), new InMemoryRawResult()),
        );

        $adapter = new TagAwareAdapter(new ArrayAdapter());
        $cachedPlatform = new CachePlatform($platform, cache: $adapter);

        // First call populates a valid entry.
        $deferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);
        $this->assertSame('First content', $deferredResult->getResult()->getContent());

        // Corrupt the stored entry with an unknown result class so denormalization fails.
        $cacheKey = (new UnicodeString('.'))->join([
            'symfony',
            (new UnicodeString('foo'))->camel(),
            md5('bar'),
        ]);
        $item = $adapter->getItem((string) $cacheKey);
        $item->set([
            'result' => ['class' => 'Unknown\\Result', 'payload' => []],
            'raw_data' => [],
            'metadata' => [],
            'cached_at' => 0,
            'cache_key' => $cacheKey,
        ]);
        $adapter->save($item);

        // The corrupted entry is dropped and the inner platform is re-invoked instead of throwing.
        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertSame('Recovered content', $secondDeferredResult->getResult()->getContent());
    }

    /**
     * When no per-call key is provided, caching engages thanks to the constructor-level cache key,
     * which acts as the default namespace.
     */
    public function testPlatformCachesUsingConstructorCacheKeyAsDefaultNamespace()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
            cacheKey: 'default',
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar');
        $this->assertSame('test content', $deferredResult->getResult()->getContent());
        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar');
        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    /**
     * An empty per-call key explicitly disables caching for that call, even when a constructor-level
     * cache key is configured.
     */
    public function testPlatformBypassesCacheWhenPerCallKeyIsEmpty()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(new PlainConverter(new TextResult('first content')), new InMemoryRawResult()),
            new DeferredResult(new PlainConverter(new TextResult('second content')), new InMemoryRawResult()),
        );

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
            cacheKey: 'default',
        );

        $firstDeferredResult = $cachedPlatform->invoke('foo', 'bar', ['prompt_cache_key' => '']);
        $this->assertSame('first content', $firstDeferredResult->getResult()->getContent());
        $this->assertFalse($firstDeferredResult->getMetadata()->has('cached_at'));

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', ['prompt_cache_key' => '']);
        $this->assertSame('second content', $secondDeferredResult->getResult()->getContent());
        $this->assertFalse($secondDeferredResult->getMetadata()->has('cached_at'));
    }

    /**
     * Cached entries are tagged with the camelized model name, so a model can be invalidated at once.
     */
    public function testPlatformInvalidatesCachedEntriesByModelTag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(new PlainConverter(new TextResult('first content')), new InMemoryRawResult()),
            new DeferredResult(new PlainConverter(new TextResult('second content')), new InMemoryRawResult()),
        );

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar', ['prompt_cache_key' => 'symfony']);
        $this->assertSame('first content', $deferredResult->getResult()->getContent());

        $this->assertTrue($cachedPlatform->invalidateTags(['foo']));

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', ['prompt_cache_key' => 'symfony']);
        $this->assertSame('second content', $secondDeferredResult->getResult()->getContent());
    }

    /**
     * Cached entries are tagged with `namespace.<cache key>`, so a whole namespace can be invalidated
     * regardless of the model.
     */
    public function testPlatformInvalidatesCachedEntriesByNamespaceTag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(
            new DeferredResult(new PlainConverter(new TextResult('first content')), new InMemoryRawResult()),
            new DeferredResult(new PlainConverter(new TextResult('second content')), new InMemoryRawResult()),
        );

        $cachedPlatform = new CachePlatform(
            $platform,
            cache: new TagAwareAdapter(new ArrayAdapter()),
        );

        $deferredResult = $cachedPlatform->invoke('foo', 'bar', ['prompt_cache_key' => 'symfony']);
        $this->assertSame('first content', $deferredResult->getResult()->getContent());

        $this->assertTrue($cachedPlatform->invalidateTags(['namespace.symfony']));

        $secondDeferredResult = $cachedPlatform->invoke('foo', 'bar', ['prompt_cache_key' => 'symfony']);
        $this->assertSame('second content', $secondDeferredResult->getResult()->getContent());
    }

    public function testPlatformInvalidateTagsReturnsFalseWithoutCache()
    {
        $cachedPlatform = new CachePlatform($this->createMock(PlatformInterface::class));

        $this->assertFalse($cachedPlatform->invalidateTags(['foo']));
    }
}

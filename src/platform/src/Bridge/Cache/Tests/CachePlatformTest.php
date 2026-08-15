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
use Symfony\AI\Platform\Bridge\Cache\CacheKeyGenerator;
use Symfony\AI\Platform\Bridge\Cache\CachePlatform;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobPlatformInterface;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
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
        $this->assertArrayHasKey(\sprintf('symfonyfoo%s', $messageBag->getId()->toRfc4122()), $adapter->getValues());
        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));
        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', $messageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertCount(3, $adapter->getValues());
        $this->assertArrayHasKey(\sprintf('symfonyfoo%s', $messageBag->getId()->toRfc4122()), $adapter->getValues());
        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertTrue($secondDeferredResult->getMetadata()->has('cached_at'));
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformCanReturnCachedResultWhenCalledTwiceWithSeparateMessageBag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(new DeferredResult(
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
        $this->assertArrayHasKey(\sprintf('symfonyfoo%s', $messageBag->getId()->toRfc4122()), $adapter->getValues());
        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));
        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', $messageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertCount(3, $adapter->getValues());
        $this->assertArrayHasKey(\sprintf('symfonyfoo%s', $messageBag->getId()->toRfc4122()), $adapter->getValues());
        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertTrue($secondDeferredResult->getMetadata()->has('cached_at'));
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));

        $secondMessageBag = new MessageBag(
            Message::ofUser('Hello there'),
        );

        $deferredResult = $cachedPlatform->invoke('foo', $secondMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertCount(5, $adapter->getValues());
        $this->assertArrayHasKey(\sprintf('symfonyfoo%s', $secondMessageBag->getId()->toRfc4122()), $adapter->getValues());
        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));
        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', $secondMessageBag, [
            'prompt_cache_key' => 'symfony',
        ]);

        $this->assertCount(5, $adapter->getValues());
        $this->assertArrayHasKey(\sprintf('symfonyfoo%s', $secondMessageBag->getId()->toRfc4122()), $adapter->getValues());
        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertTrue($secondDeferredResult->getMetadata()->has('cached_at'));
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformCachesContentObjectInput()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $document = new DocumentUrl('https://example.com/document.pdf');
        $cachedPlatform = new CachePlatform($platform, cache: new TagAwareAdapter(new ArrayAdapter()));

        $deferredResult = $cachedPlatform->invoke('foo', $document, ['prompt_cache_key' => 'symfony']);
        $this->assertSame('test content', $deferredResult->getResult()->getContent());
        $this->assertTrue($deferredResult->getMetadata()->has('cached_at'));

        $secondDeferredResult = $cachedPlatform->invoke('foo', $document, ['prompt_cache_key' => 'symfony']);
        $this->assertSame('test content', $secondDeferredResult->getResult()->getContent());
        $this->assertSame($deferredResult->getMetadata()->get('cached_at'), $secondDeferredResult->getMetadata()->get('cached_at'));
    }

    public function testPlatformDoesNotShareCacheBetweenDifferentContentObjectInputs()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $cachedPlatform = new CachePlatform($platform, cache: new TagAwareAdapter(new ArrayAdapter()));

        $cachedPlatform->invoke('foo', new DocumentUrl('https://example.com/first.pdf'), ['prompt_cache_key' => 'symfony']);
        $cachedPlatform->invoke('foo', new DocumentUrl('https://example.com/second.pdf'), ['prompt_cache_key' => 'symfony']);
    }

    public function testPlatformThrowsOnUnsupportedObjectInput()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $cachedPlatform = new CachePlatform($platform, cache: new TagAwareAdapter(new ArrayAdapter()));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported input type: "stdClass".');

        $cachedPlatform->invoke('foo', new \stdClass(), ['prompt_cache_key' => 'symfony']);
    }

    public function testPlatformUsesCustomCacheKeyGenerator()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter(new TextResult('test content')), new InMemoryRawResult(),
        ));

        $generator = new class implements CacheKeyGenerator {
            public function supports(object $input): bool
            {
                return $input instanceof \stdClass;
            }

            public function generate(object $input): string
            {
                return $input->id;
            }
        };

        $input = new \stdClass();
        $input->id = 'custom-key';

        $cachedPlatform = new CachePlatform($platform, cache: new TagAwareAdapter(new ArrayAdapter()), cacheKeyGenerators: [$generator]);

        $deferredResult = $cachedPlatform->invoke('foo', $input, ['prompt_cache_key' => 'symfony']);
        $this->assertSame('test content', $deferredResult->getResult()->getContent());

        $secondDeferredResult = $cachedPlatform->invoke('foo', $input, ['prompt_cache_key' => 'symfony']);
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

    /**
     * A job handle has to stay resolvable through the platform it came from, so the decorator must
     * forward {@see JobPlatformInterface::getJobClient()} instead of hiding it behind its own type.
     */
    public function testItForwardsJobResolutionToTheDecoratedPlatform()
    {
        $handle = (new JobHandle('task-1'))->withProvider('minimax');
        $cachedPlatform = new CachePlatform($this->createJobPlatform());

        $this->assertTrue($cachedPlatform->getJobClient($handle)->getStatus($handle)->is(JobStateCase::SUCCEEDED));
    }

    public function testItSaysSoWhenTheDecoratedPlatformHasNoJobs()
    {
        $cachedPlatform = new CachePlatform($this->createStub(PlatformInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not run asynchronous jobs');

        $cachedPlatform->getJobClient((new JobHandle('task-1'))->withProvider('minimax'));
    }

    private function createJobPlatform(): PlatformInterface&JobPlatformInterface
    {
        return new class implements PlatformInterface, JobPlatformInterface {
            public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
            {
                throw new \LogicException('Resolving a job handle must not invoke the platform.');
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                throw new \LogicException('Resolving a job handle must not need the model catalog.');
            }

            public function getJobClient(JobHandle $handle): JobClientInterface
            {
                return new class implements JobClientInterface {
                    public function supports(JobHandle $handle): bool
                    {
                        return true;
                    }

                    public function getStatus(JobHandle $handle): JobStatus
                    {
                        return new JobStatus(JobStateCase::SUCCEEDED, 'Success');
                    }

                    public function getResult(JobHandle $handle): ResultInterface
                    {
                        return new TextResult('done');
                    }
                };
            }
        };
    }
}

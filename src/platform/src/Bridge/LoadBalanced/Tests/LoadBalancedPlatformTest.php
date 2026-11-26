<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LoadBalanced\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LoadBalanced\Exception\CapacityExhaustedException;
use Symfony\AI\Platform\Bridge\LoadBalanced\LoadBalancedPlatform;
use Symfony\AI\Platform\Bridge\LoadBalanced\PlatformCapacity;
use Symfony\AI\Platform\Bridge\LoadBalanced\Provider\ConcurrencyLimitedProvider;
use Symfony\AI\Platform\Bridge\LoadBalanced\Provider\NoLimitCapacityProvider;
use Symfony\AI\Platform\Bridge\LoadBalanced\Provider\RateLimitedCapacityProvider;
use Symfony\AI\Platform\Bridge\LoadBalanced\Strategy\PlatformSelectionStrategy;
use Symfony\AI\Platform\Bridge\LoadBalanced\Strategy\RandomStrategy;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Semaphore\SemaphoreInterface;

class LoadBalancedPlatformTest extends TestCase
{
    public function testThrowsWhenNoPlatformsConfigured()
    {
        $this->expectException(InvalidArgumentException::class);

        new LoadBalancedPlatform([], new RandomStrategy());
    }

    public function testThrowsWhenAllPlatformsExhausted()
    {
        $limiter = $this->createRateLimiter(1)->create('test');
        $limiter->consume();

        $loadBalancedPlatform = new LoadBalancedPlatform([
            new PlatformCapacity(
                $this->createMock(PlatformInterface::class),
                new RateLimitedCapacityProvider($limiter),
            ),
        ], new RandomStrategy());

        $this->expectException(CapacityExhaustedException::class);
        $this->expectExceptionMessage('All platforms have exhausted their capacity.');

        $loadBalancedPlatform->invoke('model', 'foo');
    }

    public function testInvokesPlatformWhenCapacityAvailable()
    {
        $loadBalancedPlatform = new LoadBalancedPlatform([
            new PlatformCapacity(
                new InMemoryPlatform(static fn (): string => 'foo'),
                new NoLimitCapacityProvider(),
            ),
        ], new RandomStrategy());

        $result = $loadBalancedPlatform->invoke('gpt-4', 'input');

        $this->assertSame('foo', $result->asText());
    }

    public function testGetsModelCatalogFromPlatform()
    {
        $catalog = $this->createMock(ModelCatalogInterface::class);
        $platform = $this->createMock(PlatformInterface::class);

        $platform
            ->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn($catalog);

        $loadBalancedPlatform = new LoadBalancedPlatform([
            new PlatformCapacity(
                $platform,
                new NoLimitCapacityProvider(),
            ),
        ], new RandomStrategy());

        $this->assertSame($catalog, $loadBalancedPlatform->getModelCatalog());
    }

    public function testUsesPlatformSpecificModelWhenProvided()
    {
        $platform = $this->createMock(PlatformInterface::class);

        $platform
            ->expects($this->once())
            ->method('invoke')
            ->with('claude-sonnet-4', 'input')
            ->willReturn($this->createDeferredResult());

        $loadBalancedPlatform = new LoadBalancedPlatform([
            new PlatformCapacity(
                $platform,
                new NoLimitCapacityProvider(),
                model: 'claude-sonnet-4',
            ),
        ], new RandomStrategy());

        $loadBalancedPlatform->invoke('gpt-4', 'input');
    }

    public function testFallsBackToInvokeModelWhenPlatformModelNotProvided()
    {
        $platform = $this->createMock(PlatformInterface::class);

        $platform
            ->expects($this->once())
            ->method('invoke')
            ->with('gpt-4', 'input')
            ->willReturn($this->createDeferredResult());

        $loadBalancedPlatform = new LoadBalancedPlatform([
            new PlatformCapacity(
                $platform,
                new NoLimitCapacityProvider(),
            ),
        ], new RandomStrategy());

        $loadBalancedPlatform->invoke('gpt-4', 'input');
    }

    public function testSkipsExhaustedPlatform()
    {
        $exhaustedLimiter = $this->createRateLimiter(1)->create('exhausted');
        $exhaustedLimiter->consume();

        $exhaustedPlatform = $this->createMock(PlatformInterface::class);
        $exhaustedPlatform
            ->expects($this->never())
            ->method('invoke');

        $availablePlatform = $this->createMock(PlatformInterface::class);
        $availablePlatform
            ->expects($this->once())
            ->method('invoke')
            ->willReturn($this->createDeferredResult());

        $strategy = new class implements PlatformSelectionStrategy {
            public function order(array $platforms): iterable
            {
                return $platforms;
            }
        };

        $platform = new LoadBalancedPlatform([
            new PlatformCapacity($exhaustedPlatform, new RateLimitedCapacityProvider($exhaustedLimiter)),
            new PlatformCapacity($availablePlatform, new NoLimitCapacityProvider()),
        ], $strategy);

        $platform->invoke('model', 'input');
    }

    public function testReleasesConcurrencyTokenAfterDeferredResultResolves()
    {
        $releaseCounter = new \ArrayObject(['count' => 0]);
        $semaphore = $this->createSemaphore($releaseCounter);

        $loadBalancedPlatform = new LoadBalancedPlatform([
            new PlatformCapacity(
                new InMemoryPlatform(static fn (): string => 'foo'),
                new ConcurrencyLimitedProvider($semaphore),
            ),
        ], new RandomStrategy());

        $deferredResult = $loadBalancedPlatform->invoke('model', 'input');

        $this->assertSame(0, (int) $releaseCounter['count']);
        $this->assertSame('foo', $deferredResult->asText());
        $this->assertSame(1, (int) $releaseCounter['count']);
    }

    public function testReleasesConcurrencyTokenAfterStreamStopsEarly()
    {
        $releaseCounter = new \ArrayObject(['count' => 0]);
        $semaphore = $this->createSemaphore($releaseCounter);

        $loadBalancedPlatform = new LoadBalancedPlatform([
            new PlatformCapacity(
                new InMemoryPlatform(static fn (): StreamResult => new StreamResult((static function (): \Generator {
                    yield 'chunk1';
                    yield 'chunk2';
                })())),
                new ConcurrencyLimitedProvider($semaphore),
            ),
        ], new RandomStrategy());

        $stream = $loadBalancedPlatform->invoke('model', 'input')->asStream();
        $this->assertTrue($stream->valid());
        $this->assertSame('chunk1', $stream->current());
        $this->assertSame(0, (int) $releaseCounter['count']);

        unset($stream);
        gc_collect_cycles();

        $this->assertSame(1, (int) $releaseCounter['count']);
    }

    private function createDeferredResult(): DeferredResult
    {
        return new DeferredResult(
            $this->createMock(ResultConverterInterface::class),
            $this->createMock(RawResultInterface::class),
        );
    }

    private function createRateLimiter(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'policy' => 'fixed_window',
            'interval' => '1 minute',
            'limit' => $limit,
            'id' => 'test',
        ], new InMemoryStorage());
    }

    /**
     * @param \ArrayObject<string, int> $releaseCounter
     */
    private function createSemaphore(\ArrayObject $releaseCounter): SemaphoreInterface
    {
        $semaphore = $this->createMock(SemaphoreInterface::class);
        $semaphore
            ->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $semaphore
            ->expects($this->once())
            ->method('release')
            ->willReturnCallback(static function () use ($releaseCounter): void {
                ++$releaseCounter['count'];
            });

        return $semaphore;
    }
}

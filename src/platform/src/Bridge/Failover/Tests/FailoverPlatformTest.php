<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Failover\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\Failover\FailoverPlatform;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class FailoverPlatformTest extends TestCase
{
    public function testPlatformCannotBeCreatedWithoutPlatforms()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\AI\Platform\Bridge\Failover\FailoverPlatform" must have at least one platform configured.');
        $this->expectExceptionCode(0);
        new FailoverPlatform([], $this->createRateLimiterFactory());
    }

    public function testPlatformCannotPerformInvokeWithoutRemainingPlatform()
    {
        $mainPlatform = $this->createMock(PlatformInterface::class);
        $mainPlatform->expects($this->once())->method('invoke')
            ->willThrowException(new \Exception('The invoke method cannot be called from the main platform.'));

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->once())->method('invoke')
            ->willThrowException(new \Exception('The invoke method cannot be called from a failed platform.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            $mainPlatform,
        ], self::createRateLimiterFactory(), logger: $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All platforms failed.');
        $this->expectExceptionCode(0);
        $failoverPlatform->invoke('foo', 'foo');
    }

    public function testPlatformCannotRetrieveModelCatalogWithoutRemainingPlatform()
    {
        $mainPlatform = $this->createMock(PlatformInterface::class);
        $mainPlatform->expects($this->once())->method('getModelCatalog')
            ->willThrowException(new \Exception('The ModelCatalog cannot be retrieved from the main platform.'));

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->once())->method('getModelCatalog')
            ->willThrowException(new \Exception('The ModelCatalog cannot be retrieved from a failed platform.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            $mainPlatform,
        ], self::createRateLimiterFactory(), logger: $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All platforms failed.');
        $this->expectExceptionCode(0);
        $failoverPlatform->getModelCatalog();
    }

    public function testPlatformCanPerformInvokeWithRemainingPlatform()
    {
        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->once())->method('invoke')
            ->willThrowException(new \Exception('The invoke method cannot be called from a failed platform.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            new InMemoryPlatform(static fn (): string => 'foo'),
        ], self::createRateLimiterFactory(), logger: $logger);

        $result = $failoverPlatform->invoke('foo', 'foo');

        $this->assertSame('foo', $result->asText());
    }

    public function testPlatformCanRetrieveModelCatalogWithRemainingPlatform()
    {
        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->once())->method('getModelCatalog')
            ->willThrowException(new \Exception('The ModelCatalog cannot be retrieved from a failed platform.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            new InMemoryPlatform(static fn (): string => 'foo'),
        ], self::createRateLimiterFactory(), logger: $logger);

        $this->assertInstanceOf(FallbackModelCatalog::class, $failoverPlatform->getModelCatalog());
    }

    public function testPlatformCanPerformInvokeWhileRemovingPlatformAfterRetryPeriod()
    {
        $clock = new MonotonicClock();

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->any())->method('invoke')
            ->willReturnCallback(static function (): DeferredResult {
                static $call = 0;

                if (1 === ++$call) {
                    throw new \Exception('An error occurred from a failed platform while calling invoke.');
                }

                return new DeferredResult(
                    new PlainConverter(new TextResult('foo')),
                    new InMemoryRawResult(['foo' => 'bar']),
                );
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            new InMemoryPlatform(static fn (): string => 'bar'),
        ], self::createRateLimiterFactory(), logger: $logger);

        $firstResult = $failoverPlatform->invoke('foo', 'foo');

        $this->assertSame('bar', $firstResult->asText());

        $clock->sleep(4);

        $finalResult = $failedPlatform->invoke('foo', 'bar');

        $this->assertSame('foo', $finalResult->asText());
    }

    public function testPlatformCanRetrieveModelCatalogWhileRemovingPlatformAfterRetryPeriod()
    {
        $clock = new MonotonicClock();

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->any())->method('getModelCatalog')
            ->willReturnCallback(static function (): ModelCatalogInterface {
                static $call = 0;

                if (1 === ++$call) {
                    throw new \Exception('An error occurred from a failed platform while retrieving the ModelCatalog.');
                }

                return new FallbackModelCatalog();
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            new InMemoryPlatform(static fn (): string => 'bar'),
        ], self::createRateLimiterFactory(), logger: $logger);

        $this->assertInstanceOf(FallbackModelCatalog::class, $failoverPlatform->getModelCatalog());

        $clock->sleep(4);

        $this->assertInstanceOf(FallbackModelCatalog::class, $failoverPlatform->getModelCatalog());
    }

    public function testPlatformCannotPerformInvokeWhileAllPlatformFailedDuringRetryPeriod()
    {
        $clock = new MonotonicClock();

        $firstPlatform = $this->createMock(PlatformInterface::class);
        $firstPlatform->expects($this->any())->method('invoke')
            ->willReturnCallback(static function (): DeferredResult {
                static $call = 0;

                if (1 === ++$call) {
                    return new DeferredResult(
                        new PlainConverter(new TextResult('foo')),
                        new InMemoryRawResult(['foo' => 'bar']),
                    );
                }

                throw new \Exception('An error occurred from the main platform while calling invoke.');
            });

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->any())->method('invoke')
            ->willReturnCallback(static function (): DeferredResult {
                static $call = 0;

                if (1 === ++$call) {
                    throw new \Exception('An error occurred from a failing platform while calling invoke.');
                }

                throw new \Exception('An error occurred from a failing platform while calling invoke.');
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            $firstPlatform,
        ], self::createRateLimiterFactory(), logger: $logger);

        $firstResult = $failoverPlatform->invoke('foo', 'foo');

        $this->assertSame('foo', $firstResult->asText());

        $clock->sleep(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All platforms failed.');
        $this->expectExceptionCode(0);
        $failoverPlatform->invoke('foo', 'foo');
    }

    public function testPlatformCannotRetrieveModelCatalogWhileAllPlatformFailedDuringRetryPeriod()
    {
        $clock = new MonotonicClock();

        $firstPlatform = $this->createMock(PlatformInterface::class);
        $firstPlatform->expects($this->any())->method('getModelCatalog')
            ->willReturnCallback(static function (): ModelCatalogInterface {
                static $call = 0;

                if (1 === ++$call) {
                    return new FallbackModelCatalog();
                }

                throw new \Exception('An error occurred');
            });

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->any())->method('getModelCatalog')
            ->willReturnCallback(static function (): ModelCatalogInterface {
                static $call = 0;

                if (1 === ++$call) {
                    throw new \Exception('An error occurred from a failing platform');
                }

                throw new \Exception('An error occurred from a failing platform');
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            $firstPlatform,
        ], self::createRateLimiterFactory(), logger: $logger);

        $this->assertInstanceOf(FallbackModelCatalog::class, $failoverPlatform->getModelCatalog());

        $clock->sleep(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All platforms failed.');
        $this->expectExceptionCode(0);
        $failoverPlatform->getModelCatalog();
    }

    public function testPlatformCanPerformInvokeWhileAllPlatformFailedDuringRetryPeriodThenRecovered()
    {
        $clock = new MonotonicClock();

        $firstPlatform = $this->createMock(PlatformInterface::class);
        $firstPlatform->expects($this->any())->method('invoke')
            ->willReturnCallback(static function (): DeferredResult {
                static $call = 0;

                if (4 === ++$call) {
                    throw new \Exception('An error occurred from the first platform while calling invoke.');
                }

                return new DeferredResult(
                    new PlainConverter(new TextResult('foo')),
                    new InMemoryRawResult(['foo' => 'bar']),
                );
            });

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->any())->method('invoke')
            ->willReturnCallback(static function (): DeferredResult {
                static $call = 0;

                if (1 === ++$call) {
                    throw new \Exception('An error occurred from a failing platform');
                }

                if (3 === ++$call) {
                    throw new \Exception('An error occurred from a failing platform');
                }

                throw new \Exception('An error occurred from a failing platform');
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            $firstPlatform,
        ], self::createRateLimiterFactory(), logger: $logger);

        $failoverPlatform->invoke('foo', 'foo');

        $clock->sleep(1);

        $failoverPlatform->invoke('foo', 'foo');

        $clock->sleep(1);

        $finalResult = $failoverPlatform->invoke('foo', 'foo');

        $this->assertSame('foo', $finalResult->asText());
    }

    public function testPlatformCanRetrieveModelCatalogWhileAllPlatformFailedDuringRetryPeriodThenRecovered()
    {
        $clock = new MonotonicClock();

        $firstPlatform = $this->createMock(PlatformInterface::class);
        $firstPlatform->expects($this->any())->method('getModelCatalog')
            ->willReturnCallback(static function (): ModelCatalogInterface {
                static $call = 0;

                if (4 === ++$call) {
                    throw new \Exception('An error occurred from the first platform while retrieving the model catalog.');
                }

                return new FallbackModelCatalog();
            });

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->any())->method('getModelCatalog')
            ->willReturnCallback(static function (): ModelCatalogInterface {
                static $call = 0;

                if (1 === ++$call) {
                    throw new \Exception('An error occurred from a failing platform');
                }

                if (3 === ++$call) {
                    throw new \Exception('An error occurred from a failing platform');
                }

                throw new \Exception('An error occurred from a failing platform');
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))->method('error');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            $firstPlatform,
        ], self::createRateLimiterFactory(), logger: $logger);

        $failoverPlatform->getModelCatalog();

        $clock->sleep(1);

        $failoverPlatform->getModelCatalog();

        $clock->sleep(1);

        $failoverPlatform->getModelCatalog();
    }

    private static function createRateLimiterFactory(): RateLimiterFactoryInterface
    {
        return new RateLimiterFactory([
            'policy' => 'sliding_window',
            'id' => 'failover',
            'interval' => '60 seconds',
            'limit' => 3,
        ], new InMemoryStorage());
    }
}

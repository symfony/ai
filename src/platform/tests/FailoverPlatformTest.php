<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FailoverPlatform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

final class FailoverPlatformTest extends TestCase
{
    public function testPlatformCanPerformInvokeWithoutRemainingPlatform()
    {
        $mainPlatform = $this->createMock(PlatformInterface::class);
        $mainPlatform->expects($this->once())->method('invoke')->willThrowException(new \Exception());

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->once())->method('invoke')->willThrowException(new \Exception());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            $mainPlatform,
        ], logger: $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All platforms failed.');
        $this->expectExceptionCode(0);
        $failoverPlatform->invoke('foo', 'foo');
    }

    public function testPlatformCanPerformInvokeWithRemainingPlatform()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);

        $resultConverter = self::createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->never())->method('convert');

        $result = new DeferredResult($resultConverter, $rawHttpResult);

        $mainPlatform = $this->createMock(PlatformInterface::class);
        $mainPlatform->expects($this->once())->method('invoke')->willReturn($result);

        $failedPlatform = $this->createMock(PlatformInterface::class);
        $failedPlatform->expects($this->once())->method('invoke')->willThrowException(new \Exception());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $failoverPlatform = new FailoverPlatform([
            $failedPlatform,
            $mainPlatform,
        ], logger: $logger);

        $finalResult = $failoverPlatform->invoke('foo', 'foo');

        $this->assertSame($finalResult, $result);
    }

    public function testPlatformCanPerformInvokeWhileRemovingPlatformAfterRetryPeriod()
    {
    }
}

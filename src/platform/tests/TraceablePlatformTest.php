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
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TraceablePlatform;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TraceablePlatformTest extends TestCase
{
    public function testResetClearsCallsAndResultCache()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $traceablePlatform = new TraceablePlatform($platform);
        $result = new TextResult('Assistant response');

        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter($result), $this->createStub(RawResultInterface::class)));

        $traceablePlatform->invoke('gpt-4o', 'Hello');
        $this->assertCount(1, $traceablePlatform->getCalls());
        $this->assertSame('gpt-4o', $traceablePlatform->getCalls()[0]['model']);
        $this->assertSame('Hello', $traceablePlatform->getCalls()[0]['input']);

        $oldCache = $traceablePlatform->getResultCache();

        $traceablePlatform->reset();

        $this->assertCount(0, $traceablePlatform->getCalls());
        $this->assertNotSame($oldCache, $traceablePlatform->getResultCache());
        $this->assertInstanceOf(\WeakMap::class, $traceablePlatform->getResultCache());
    }

    public function testCancellingWrappedStreamStopsIterationCleanly()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->atLeastOnce())->method('cancel');

        $rawResult = new RawHttpResult($response);
        $originalStream = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
            yield new TextDelta('chunk3');
            yield new TextDelta('chunk4');
        })());

        $innerPlatform = $this->createStub(PlatformInterface::class);
        $innerPlatform->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter($originalStream),
            $rawResult,
        ));

        $traceable = new TraceablePlatform($innerPlatform);
        $wrappedDeferred = $traceable->invoke('gpt-4o', 'Tell me a story', ['stream' => true]);

        $collected = [];
        foreach ($wrappedDeferred->asStream() as $delta) {
            $collected[] = $delta;
            if (2 === \count($collected)) {
                $wrappedDeferred->cancel();
            }
        }

        $this->assertCount(2, $collected);
        $this->assertTrue($wrappedDeferred->isCancelled());
        $this->assertTrue($rawResult->isCancelled());
    }
}

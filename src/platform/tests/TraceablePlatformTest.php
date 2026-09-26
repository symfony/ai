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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TraceablePlatform;
use Symfony\Component\Stopwatch\Stopwatch;

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

    public function testResetDoesNotBreakStreamBeingConsumed()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $traceablePlatform = new TraceablePlatform($platform);

        $stream = new StreamResult((static function () {
            yield new TextDelta('first ');
            yield new TextDelta('second');
        })());
        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter($stream), $this->createStub(RawResultInterface::class)));

        $generator = $traceablePlatform->invoke('gpt-4o', 'Hello', ['stream' => true])->asStream();
        $this->assertEquals(new TextDelta('first '), $generator->current());

        $traceablePlatform->reset();

        $generator->next();
        $this->assertEquals(new TextDelta('second'), $generator->current());

        $generator->next();
        $this->assertFalse($generator->valid());
        $this->assertCount(0, $traceablePlatform->getResultCache());
    }

    public function testInvokeWithModelInstanceRecordsModelName()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $traceablePlatform = new TraceablePlatform($platform);
        $result = new TextResult('Assistant response');

        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter($result), $this->createStub(RawResultInterface::class)));

        $traceablePlatform->invoke(new Model('gpt-4o'), 'Hello');

        $this->assertSame('gpt-4o', $traceablePlatform->getCalls()[0]['model']);
    }

    public function testStopwatchEventSpansUntilResultIsConverted()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $stopwatch = new Stopwatch();
        $traceablePlatform = new TraceablePlatform($platform, $stopwatch);

        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter(new TextResult('Assistant response')), $this->createStub(RawResultInterface::class)));

        $deferredResult = $traceablePlatform->invoke('gpt-4o', 'Hello');

        $event = $stopwatch->getEvent('ai.platform.invoke "gpt-4o"');
        $this->assertSame('ai', $event->getCategory());
        $this->assertTrue($event->isStarted());

        $deferredResult->getResult();
        $deferredResult->getResult();

        $this->assertFalse($event->isStarted());
        $this->assertCount(1, $event->getPeriods());
    }

    public function testStopwatchEventStopsOnConversionFailure()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $stopwatch = new Stopwatch();
        $traceablePlatform = new TraceablePlatform($platform, $stopwatch);

        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('convert')->willThrowException(new RuntimeException('Conversion failed'));
        $platform->method('invoke')->willReturn(new DeferredResult($converter, $this->createStub(RawResultInterface::class)));

        $deferredResult = $traceablePlatform->invoke('gpt-4o', 'Hello');

        try {
            $deferredResult->getResult();
            $this->fail('Expected conversion to fail.');
        } catch (RuntimeException) {
        }

        $event = $stopwatch->getEvent('ai.platform.invoke "gpt-4o"');
        $this->assertFalse($event->isStarted());
        $this->assertCount(1, $event->getPeriods());
    }

    public function testStopwatchEventStopsOnInvocationFailure()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $stopwatch = new Stopwatch();
        $traceablePlatform = new TraceablePlatform($platform, $stopwatch);

        $platform->method('invoke')->willThrowException(new RuntimeException('Invocation failed'));

        try {
            $traceablePlatform->invoke('gpt-4o', 'Hello');
            $this->fail('Expected invocation to fail.');
        } catch (RuntimeException) {
        }

        $event = $stopwatch->getEvent('ai.platform.invoke "gpt-4o"');
        $this->assertFalse($event->isStarted());
        $this->assertCount(1, $event->getPeriods());
    }

    public function testStopwatchEventSpansUntilStreamIsConsumed()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $stopwatch = new Stopwatch();
        $traceablePlatform = new TraceablePlatform($platform, $stopwatch);

        $stream = new StreamResult((static function () {
            yield new TextDelta('Hello ');
            yield new TextDelta('World');
        })());
        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter($stream), $this->createStub(RawResultInterface::class)));

        $deferredResult = $traceablePlatform->invoke('gpt-4o', 'Hello', ['stream' => true]);

        $event = $stopwatch->getEvent('ai.platform.invoke "gpt-4o"');
        $generator = $deferredResult->asStream();
        $generator->current();

        $this->assertTrue($event->isStarted());

        iterator_to_array($generator);

        $this->assertFalse($event->isStarted());
        $this->assertCount(1, $event->getPeriods());
    }

    public function testStopwatchEventIsNotStoppedTwiceWhenAlreadyClosed()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $stopwatch = new Stopwatch();
        $traceablePlatform = new TraceablePlatform($platform, $stopwatch);

        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter(new TextResult('Assistant response')), $this->createStub(RawResultInterface::class)));

        $deferredResult = $traceablePlatform->invoke('gpt-4o', 'Hello');

        // The profiler closes pending events when collecting, before the result may be consumed
        $event = $stopwatch->getEvent('ai.platform.invoke "gpt-4o"');
        $event->ensureStopped();

        $deferredResult->getResult();

        $this->assertCount(1, $event->getPeriods());
    }
}

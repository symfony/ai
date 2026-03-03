<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Profiler\TraceablePlatform;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;

final class TraceablePlatformTest extends TestCase
{
    public function testResetClearsCallsAndResultCache()
    {
        $platform = $this->createStub(PlatformInterface::class);
        $traceablePlatform = new TraceablePlatform($platform);
        $result = new TextResult('Assistant response');

        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter($result), $this->createStub(RawResultInterface::class)));

        $traceablePlatform->invoke('gpt-4o', 'Hello');
        $this->assertCount(1, $traceablePlatform->calls);
        $this->assertSame('gpt-4o', $traceablePlatform->calls[0]['model']);
        $this->assertSame('Hello', $traceablePlatform->calls[0]['input']);

        $oldCache = $traceablePlatform->resultCache;

        $traceablePlatform->reset();

        $this->assertCount(0, $traceablePlatform->calls);
        $this->assertNotSame($oldCache, $traceablePlatform->resultCache);
        $this->assertInstanceOf(\WeakMap::class, $traceablePlatform->resultCache);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class StreamResultTest extends TestCase
{
    public function testGetContent()
    {
        $generator = (static function () {
            yield new TextDelta('data1');
            yield new TextDelta('data2');
        })();

        $result = new StreamResult($generator);
        $this->assertInstanceOf(\Generator::class, $result->getContent());

        $content = iterator_to_array($result->getContent());

        $this->assertCount(2, $content);
        $this->assertInstanceOf(TextDelta::class, $content[0]);
        $this->assertSame('data1', $content[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $content[1]);
        $this->assertSame('data2', $content[1]->getText());
    }

    public function testGetDelta()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
        })());

        $capturedDeltas = [];
        $result->addListener(new class($capturedDeltas) extends AbstractStreamListener {
            /** @param array<DeltaInterface> $capturedDeltas */
            public function __construct(private array &$capturedDeltas) /* @phpstan-ignore property.onlyWritten */
            {
            }

            public function onDelta(DeltaEvent $event): void
            {
                $this->capturedDeltas[] = $event->getDelta();
            }
        });

        iterator_to_array($result->getContent());

        $this->assertCount(2, $capturedDeltas);
        $this->assertInstanceOf(TextDelta::class, $capturedDeltas[0]);
        $this->assertSame('chunk1', $capturedDeltas[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $capturedDeltas[1]);
        $this->assertSame('chunk2', $capturedDeltas[1]->getText());
    }

    public function testListenerCanAddMetadataDuringStreaming()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
        })());

        // Listener that adds metadata when it sees a specific delta
        $result->addListener(new class extends AbstractStreamListener {
            public function onDelta(DeltaEvent $event): void
            {
                $delta = $event->getDelta();
                if ($delta instanceof TextDelta && 'chunk2' === $delta->getText()) {
                    $event->getResult()->getMetadata()->add('seen_chunk2', true);
                }
            }
        });

        // Before consumption, metadata is empty
        $this->assertFalse($result->getMetadata()->has('seen_chunk2'));

        iterator_to_array($result->getContent());

        // After consumption, metadata is populated
        $this->assertTrue($result->getMetadata()->has('seen_chunk2'));
    }

    public function testCancelStopsIterationMidStream()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
            yield new TextDelta('chunk3');
            yield new TextDelta('chunk4');
        })());

        $collected = [];
        foreach ($result->getContent() as $delta) {
            $collected[] = $delta;
            if (2 === \count($collected)) {
                $result->cancel();
            }
        }

        $this->assertCount(2, $collected);
        $this->assertTrue($result->isCancelled());
    }

    public function testCancelBeforeIterationYieldsNothing()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
        })());

        $result->cancel();

        $collected = iterator_to_array($result->getContent());

        $this->assertSame([], $collected);
        $this->assertTrue($result->isCancelled());
    }

    public function testCancelPropagatesToRawHttpResult()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
        })());
        $result->setRawResult(new RawHttpResult($response));

        $this->assertFalse($result->isCancelled());

        $result->cancel();

        $this->assertTrue($result->isCancelled());
    }

    public function testCancelIsIdempotent()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
        })());
        $result->setRawResult(new RawHttpResult($response));

        $result->cancel();
        $result->cancel();
        $result->cancel();

        $this->assertTrue($result->isCancelled());
    }

    public function testCancelSwallowsTransportExceptionFromUnderlyingStream()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
            // Simulates the HTTP response throwing after cancellation has propagated
            // to the underlying Symfony HttpClient response (connection aborted).
            throw new TransportException('Response has been canceled.');
        })());

        $collected = [];
        foreach ($result->getContent() as $delta) {
            $collected[] = $delta;
            if (1 === \count($collected)) {
                $result->cancel();
            }
        }

        $this->assertCount(1, $collected);
        $this->assertTrue($result->isCancelled());
    }

    public function testListenerCanStopIterationViaDeltaEvent()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            yield new TextDelta('chunk2');
            yield new TextDelta('chunk3');
            yield new TextDelta('chunk4');
        })());

        $result->addListener(new class extends AbstractStreamListener {
            private int $count = 0;

            public function onDelta(DeltaEvent $event): void
            {
                ++$this->count;
                if (2 === $this->count) {
                    $event->stop();
                }
            }
        });

        $collected = iterator_to_array($result->getContent());

        $this->assertCount(1, $collected);
        $this->assertTrue($result->isCancelled());
    }

    public function testTransportExceptionIsRethrownWhenNotCancelled()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('chunk1');
            throw new TransportException('Network failure unrelated to cancel.');
        })());

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('Network failure unrelated to cancel.');

        foreach ($result->getContent() as $delta) {
            // iterate until the throw
        }
    }
}

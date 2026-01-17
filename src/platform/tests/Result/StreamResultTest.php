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
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\ChunkEvent;
use Symfony\AI\Platform\Result\StreamResult;

final class StreamResultTest extends TestCase
{
    public function testGetContent()
    {
        $generator = (static function () {
            yield 'data1';
            yield 'data2';
        })();

        $result = new StreamResult($generator);
        $this->assertInstanceOf(\Generator::class, $result->getContent());

        $content = iterator_to_array($result->getContent());

        $this->assertCount(2, $content);
        $this->assertSame('data1', $content[0]);
        $this->assertSame('data2', $content[1]);
    }

    public function testGetChunk()
    {
        $result = new StreamResult((function () {
            yield 'chunk1';
            yield 'chunk2';
        })());

        $capturedChunks = [];
        $result->addListener(new class($capturedChunks) extends AbstractStreamListener {
            /** @param array<string> $capturedChunks */
            public function __construct(private array &$capturedChunks) /* @phpstan-ignore property.onlyWritten */
            {
            }

            public function onChunk(ChunkEvent $event): void
            {
                $this->capturedChunks[] = $event->getChunk();
            }
        });

        iterator_to_array($result->getContent());

        $this->assertSame(['chunk1', 'chunk2'], $capturedChunks);
    }

    public function testListenerCanAddMetadataDuringStreaming()
    {
        $result = new StreamResult((function () {
            yield 'chunk1';
            yield 'chunk2';
        })());

        // Listener that adds metadata when it sees a specific chunk
        $result->addListener(new class extends AbstractStreamListener {
            public function onChunk(ChunkEvent $event): void
            {
                if ('chunk2' === $event->getChunk()) {
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

    public function testAfterChunkConsumedCallback()
    {
        $innerResult = new StreamResult((function () {
            yield 'inner1';
            yield 'inner2';
        })());

        // Listener that adds metadata during inner stream consumption
        $innerResult->addListener(new class extends AbstractStreamListener {
            public function onChunk(ChunkEvent $event): void
            {
                if ('inner2' === $event->getChunk()) {
                    $event->getResult()->getMetadata()->add('inner_data', 'from_inner');
                }
            }
        });

        $outerResult = new StreamResult((function () {
            yield 'outer1';
            yield 'REPLACE_ME';
        })());

        // Listener that replaces a chunk and registers a callback for metadata propagation
        $outerResult->addListener(new class($innerResult) extends AbstractStreamListener {
            public function __construct(private StreamResult $innerResult)
            {
            }

            public function onChunk(ChunkEvent $event): void
            {
                if ('REPLACE_ME' === $event->getChunk()) {
                    $event->setChunk($this->innerResult->getContent());
                    $event->afterChunkConsumed(function () use ($event) {
                        // Propagate metadata after inner stream is consumed
                        foreach ($this->innerResult->getMetadata()->all() as $key => $value) {
                            $event->getResult()->getMetadata()->add($key, $value);
                        }
                    });
                }
            }
        });

        $content = iterator_to_array($outerResult->getContent(), false);

        $this->assertSame(['outer1', 'inner1', 'inner2'], $content);

        // Metadata from inner result IS propagated to outer via callback
        $this->assertTrue($outerResult->getMetadata()->has('inner_data'));
        $this->assertSame('from_inner', $outerResult->getMetadata()->get('inner_data'));
    }

    public function testWithoutCallbackMetadataIsNotPropagated()
    {
        // When no afterChunkConsumed callback is registered,
        // metadata is not automatically propagated.

        $innerResult = new StreamResult((function () {
            yield 'inner1';
            yield 'inner2';
        })());

        $innerResult->addListener(new class extends AbstractStreamListener {
            public function onChunk(ChunkEvent $event): void
            {
                if ('inner2' === $event->getChunk()) {
                    $event->getResult()->getMetadata()->add('inner_data', 'from_inner');
                }
            }
        });

        $outerResult = new StreamResult((function () {
            yield 'outer1';
            yield 'REPLACE_ME';
        })());

        // Listener that replaces with just the content, no callback
        $outerResult->addListener(new class($innerResult) extends AbstractStreamListener {
            public function __construct(private StreamResult $innerResult)
            {
            }

            public function onChunk(ChunkEvent $event): void
            {
                if ('REPLACE_ME' === $event->getChunk()) {
                    // Only setting the content, no afterChunkConsumed callback
                    $event->setChunk($this->innerResult->getContent());
                }
            }
        });

        $content = iterator_to_array($outerResult->getContent(), false);

        $this->assertSame(['outer1', 'inner1', 'inner2'], $content);

        // No callback registered - inner metadata is NOT on outer
        $this->assertFalse($outerResult->getMetadata()->has('inner_data'));
        // But inner result does have its metadata
        $this->assertTrue($innerResult->getMetadata()->has('inner_data'));
    }
}

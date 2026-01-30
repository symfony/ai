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
        $result = new StreamResult((static function () {
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
        $result = new StreamResult((static function () {
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
}

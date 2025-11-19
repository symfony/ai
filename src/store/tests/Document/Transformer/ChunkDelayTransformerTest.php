<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Transformer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\ChunkDelayTransformer;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ChunkDelayTransformerTest extends TestCase
{
    public function testDefaultChunkSizeAndDelay()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->never())
            ->method('sleep');

        $transformer = new ChunkDelayTransformer($clock);

        $documents = [];
        for ($i = 0; $i < 30; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'content-'.$i);
        }

        $result = iterator_to_array($transformer->transform($documents));

        $this->assertCount(30, $result);
        for ($i = 0; $i < 30; ++$i) {
            $this->assertSame('content-'.$i, $result[$i]->getContent());
        }
    }

    public function testSleepsAfterChunkSize()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->once())
            ->method('sleep')
            ->with(5);

        $transformer = new ChunkDelayTransformer($clock);

        $documents = [];
        for ($i = 0; $i < 100; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'content-'.$i);
        }

        $result = iterator_to_array($transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 50,
            ChunkDelayTransformer::OPTION_DELAY => 5,
        ]));

        $this->assertCount(100, $result);
    }

    public function testCustomChunkSizeAndDelay()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->once())
            ->method('sleep')
            ->with(2);

        $transformer = new ChunkDelayTransformer($clock);

        $documents = [];
        for ($i = 0; $i < 40; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'content-'.$i);
        }

        $result = iterator_to_array($transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 10,
            ChunkDelayTransformer::OPTION_DELAY => 2,
        ]));

        $this->assertCount(40, $result);
    }

    public function testNoSleepWhenDelayIsZero()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->never())
            ->method('sleep');

        $transformer = new ChunkDelayTransformer($clock);

        $documents = [];
        for ($i = 0; $i < 20; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'content-'.$i);
        }

        $result = iterator_to_array($transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 5,
            ChunkDelayTransformer::OPTION_DELAY => 0,
        ]));

        $this->assertCount(20, $result);
    }

    public function testYieldsDocumentsInCorrectOrder()
    {
        $clock = $this->createMock(ClockInterface::class);
        $transformer = new ChunkDelayTransformer($clock);

        $documents = [
            new TextDocument(Uuid::v4(), 'first'),
            new TextDocument(Uuid::v4(), 'second'),
            new TextDocument(Uuid::v4(), 'third'),
        ];

        $result = iterator_to_array($transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 2,
            ChunkDelayTransformer::OPTION_DELAY => 1,
        ]));

        $this->assertSame('first', $result[0]->getContent());
        $this->assertSame('second', $result[1]->getContent());
        $this->assertSame('third', $result[2]->getContent());
    }

    public function testHandlesEmptyIterable()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->never())
            ->method('sleep');

        $transformer = new ChunkDelayTransformer($clock);

        $result = iterator_to_array($transformer->transform([]));

        $this->assertCount(0, $result);
    }

    public function testSingleDocument()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->once())
            ->method('sleep')
            ->with(5);

        $transformer = new ChunkDelayTransformer($clock);

        $documents = [new TextDocument(Uuid::v4(), 'single')];

        $result = iterator_to_array($transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 1,
            ChunkDelayTransformer::OPTION_DELAY => 5,
        ]));

        $this->assertCount(1, $result);
        $this->assertSame('single', $result[0]->getContent());
    }

    public function testExactlyChunkSizeDocuments()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->once())
            ->method('sleep')
            ->with(3);

        $transformer = new ChunkDelayTransformer($clock);

        $documents = [];
        for ($i = 0; $i < 10; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'content-'.$i);
        }

        $result = iterator_to_array($transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 10,
            ChunkDelayTransformer::OPTION_DELAY => 3,
        ]));

        $this->assertCount(10, $result);
    }

    public function testMultipleExactChunks()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->once())
            ->method('sleep')
            ->with(1);

        $transformer = new ChunkDelayTransformer($clock);

        $documents = [];
        for ($i = 0; $i < 15; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'content-'.$i);
        }

        $result = iterator_to_array($transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 5,
            ChunkDelayTransformer::OPTION_DELAY => 1,
        ]));

        $this->assertCount(15, $result);
    }

    public function testLazyEvaluation()
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->once())
            ->method('sleep')
            ->with(1);

        $transformer = new ChunkDelayTransformer($clock);

        $documents = [];
        for ($i = 0; $i < 10; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'content-'.$i);
        }

        $generator = $transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 3,
            ChunkDelayTransformer::OPTION_DELAY => 1,
        ]);

        $count = 0;
        foreach ($generator as $document) {
            ++$count;
            if (5 === $count) {
                break;
            }
        }

        $this->assertSame(5, $count);
    }

    public function testWithMockClock()
    {
        $clock = new MockClock();
        $transformer = new ChunkDelayTransformer($clock);

        $documents = [];
        for ($i = 0; $i < 10; ++$i) {
            $documents[] = new TextDocument(Uuid::v4(), 'content-'.$i);
        }

        $startTime = $clock->now();

        iterator_to_array($transformer->transform($documents, [
            ChunkDelayTransformer::OPTION_CHUNK_SIZE => 5,
            ChunkDelayTransformer::OPTION_DELAY => 30,
        ]));

        $endTime = $clock->now();
        $elapsed = $endTime->getTimestamp() - $startTime->getTimestamp();

        $this->assertSame(30, $elapsed);
    }
}

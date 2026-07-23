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
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
use Symfony\AI\Platform\Result\Stream\ErrorEvent;
use Symfony\AI\Platform\Result\StreamResult;

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

    public function testListenerIsNotifiedOnErrorNotOnCompleteWhenStreamThrows()
    {
        $exception = new \RuntimeException('stream failed mid-flight');

        $result = new StreamResult((static function () use ($exception): \Generator {
            yield new TextDelta('chunk1');

            throw $exception;
        })());

        $listener = new class extends AbstractStreamListener {
            public ?\Throwable $error = null;
            public bool $completed = false;

            public function onComplete(CompleteEvent $event): void
            {
                $this->completed = true;
            }

            public function onError(ErrorEvent $event): void
            {
                $this->error = $event->getError();
            }
        };
        $result->addListener($listener);

        $caught = null;
        try {
            iterator_to_array($result->getContent());
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertSame($exception, $caught);
        $this->assertSame($exception, $listener->error);
        $this->assertFalse($listener->completed);
    }

    public function testErrorListenerReadsMetadataMergedBeforeTheThrow()
    {
        $result = new StreamResult((static function (): \Generator {
            yield new TextDelta('chunk1');

            throw new \RuntimeException('truncated at the output token ceiling');
        })());

        $result->addListener(new class extends AbstractStreamListener {
            public function onDelta(DeltaEvent $event): void
            {
                $event->getResult()->getMetadata()->add('token_usage', true);
            }
        });

        $listener = new class extends AbstractStreamListener {
            public bool $sawUsageAtError = false;

            public function onError(ErrorEvent $event): void
            {
                $this->sawUsageAtError = $event->getResult()->getMetadata()->has('token_usage');
            }
        };
        $result->addListener($listener);

        try {
            iterator_to_array($result->getContent());
        } catch (\RuntimeException) {
        }

        $this->assertTrue($listener->sawUsageAtError);
    }
}

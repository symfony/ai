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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
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

    public function testGetAssistantMessageReturnsTheDrainedTurn()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('Hello ');
            yield new ThinkingComplete('Pondering.', 'sig-abc');
            yield new TextDelta('World');
        })());

        iterator_to_array($result->getContent());

        $this->assertEquals(
            [new Text('Hello '), new Thinking('Pondering.', 'sig-abc'), new Text('World')],
            $result->getAssistantMessage()->getContent(),
        );
    }

    public function testGetAssistantMessageDrainsAStreamNobodyRead()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('Never ');
            yield new TextDelta('iterated');
        })());

        $this->assertSame('Never iterated', $result->getAssistantMessage()->asText());
    }

    public function testOfAssistantAcceptsAStreamedResultLikeABufferedOne()
    {
        $result = new StreamResult((static function () {
            yield new ThinkingComplete('Pondering.', 'sig-abc');
            yield new TextDelta('The answer is 42.');
        })());

        $message = Message::ofAssistant($result);

        $this->assertEquals(
            [new Thinking('Pondering.', 'sig-abc'), new Text('The answer is 42.')],
            $message->getContent(),
        );
    }

    public function testGetAssistantMessageDrainsAStreamAbandonedMidIteration()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('one ');
            yield new TextDelta('two ');
            yield new TextDelta('three');
        })());

        $stream = $result->getContent();
        foreach ($stream as $delta) {
            break;
        }

        $this->assertSame('one two three', $result->getAssistantMessage()->asText());
        $this->assertSame('one two three', Message::ofAssistant($result)->asText());
    }

    public function testGetAssistantMessageReturnsThePartialTurnOfADiscardedStream()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('one ');
            yield new TextDelta('two ');
        })());

        // nothing holds the generator once the loop drops it, and a fresh one would re-read the
        // delta this one stopped on
        foreach ($result->getContent() as $delta) {
            break;
        }

        $this->assertSame('one ', $result->getAssistantMessage()->asText());
    }

    public function testGetAssistantMessageReturnsThePartialTurnAfterTheStreamThrew()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('one ');
            yield new TextDelta('two ');

            throw new \RuntimeException('upstream failed');
        })());

        try {
            iterator_to_array($result->getContent());
            $this->fail('the stream was expected to throw');
        } catch (\RuntimeException) {
        }

        // a stream that threw cannot be resumed
        $this->assertSame('one two ', $result->getAssistantMessage()->asText());
    }

    public function testADeltaRewrittenInPlaceDoesNotDiscardTheTurn()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('My number is ');
            yield new TextDelta('555-0100');
            yield new TextDelta(', call me.');
        })());

        // a rewrite leaves the rest of the collected turn intact
        $result->addListener(new class extends AbstractStreamListener {
            public function onDelta(DeltaEvent $event): void
            {
                $delta = $event->getDelta();

                if ($delta instanceof TextDelta && str_contains($delta->getText(), '555')) {
                    $event->setDelta(new TextDelta('[redacted]'));
                }
            }
        });

        iterator_to_array($result->getContent());

        $this->assertSame('My number is [redacted], call me.', $result->getAssistantMessage()->asText());
    }

    public function testADeltaReplacedByAStreamOfItsOwnDoesNotDiscardTheTurn()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('{"name":');
            yield new TextDelta('"Berlin"}');
        })());

        // what PartialObjectStreamListener does: the original delta is re-yielded alongside a snapshot
        $result->addListener(new class extends AbstractStreamListener {
            public function onDelta(DeltaEvent $event): void
            {
                $delta = $event->getDelta();

                $event->setDelta((static function () use ($delta) {
                    yield $delta;
                })());
            }
        });

        iterator_to_array($result->getContent(), false);

        $this->assertSame('{"name":"Berlin"}', $result->getAssistantMessage()->asText());
    }

    public function testAListenerReadsTheTurnFromInsideTheStream()
    {
        $result = new StreamResult((static function () {
            yield new TextDelta('Collected');
        })());

        $listener = new class extends AbstractStreamListener {
            public ?string $seen = null;

            public function onComplete(CompleteEvent $event): void
            {
                // runs inside the generator, so reading the turn must not resume it
                $this->seen = $event->getResult()->getAssistantMessage()->asText();
            }
        };
        $result->addListener($listener);

        iterator_to_array($result->getContent());

        $this->assertSame('Collected', $listener->seen);
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

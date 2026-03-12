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
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
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
            /** @param array<TextDelta> $capturedDeltas */
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
}

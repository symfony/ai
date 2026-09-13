<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result\Stream;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\AssistantMessageStreamListener;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingSignature;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\WebSearchComplete;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\WebSearchResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageStreamListenerTest extends TestCase
{
    /**
     * @param list<DeltaInterface>   $deltas
     * @param list<ContentInterface> $expected
     */
    #[DataProvider('provideStreams')]
    public function testItRebuildsTheAssistantTurn(array $deltas, array $expected)
    {
        $listener = new AssistantMessageStreamListener();

        foreach ($deltas as $delta) {
            $listener->accumulate($delta);
        }

        $this->assertEquals($expected, $listener->getAssistantMessage()->getContent());
    }

    /**
     * @return iterable<string, array{0: list<DeltaInterface>, 1: list<ContentInterface>}>
     */
    public static function provideStreams(): iterable
    {
        $toolCall = new ToolCall('call_1', 'clock', []);
        $other = new ToolCall('call_2', 'weather', ['city' => 'Paris']);

        yield 'text is concatenated into a single part' => [
            [new TextDelta('Hello '), new TextDelta('World')],
            [new Text('Hello World')],
        ];

        yield 'empty text deltas carry no content' => [
            [new TextDelta(''), new TextDelta('Hi'), new TextDelta('')],
            [new Text('Hi')],
        ];

        yield 'anthropic thinking with an inline signature' => [
            [
                new ThinkingStart(),
                new ThinkingDelta('Let me '),
                new ThinkingDelta('think.'),
                new ThinkingSignature('sig-abc'),
                new ThinkingComplete('Let me think.', 'sig-abc'),
                new TextDelta('The answer is 42.'),
            ],
            [new Thinking('Let me think.', 'sig-abc'), new Text('The answer is 42.')],
        ];

        yield 'signature arriving after the thinking block closed' => [
            [
                new ThinkingStart(),
                new ThinkingDelta('Pondering.'),
                new ThinkingComplete('Pondering.'),
                new ThinkingSignature('{"type":"reasoning","id":"rs_1"}'),
                new TextDelta('Done.'),
            ],
            [new Thinking('Pondering.', '{"type":"reasoning","id":"rs_1"}'), new Text('Done.')],
        ];

        yield 'signature without any thinking block' => [
            [
                new ThinkingSignature('{"type":"reasoning","id":"rs_1"}'),
                new TextDelta('Answer.'),
            ],
            [new Thinking('', '{"type":"reasoning","id":"rs_1"}'), new Text('Answer.')],
        ];

        yield 'two reasoning items keep separate signatures' => [
            [
                new ThinkingSignature('{"type":"reasoning","id":"rs_1"}'),
                new ThinkingSignature('{"type":"reasoning","id":"rs_2"}'),
            ],
            [
                new Thinking('', '{"type":"reasoning","id":"rs_1"}'),
                new Thinking('', '{"type":"reasoning","id":"rs_2"}'),
            ],
        ];

        yield 'a thinking block opened but never filled is dropped' => [
            [new ThinkingStart(), new TextDelta('Straight to it.')],
            [new Text('Straight to it.')],
        ];

        yield 'announced tool call keeps its position' => [
            [
                new TextDelta('Before. '),
                new ToolCallStart('call_1', 'clock'),
                new TextDelta('After.'),
                new ToolCallComplete([$toolCall]),
            ],
            [new Text('Before. '), $toolCall, new Text('After.')],
        ];

        yield 'unannounced tool calls are appended rather than dropped' => [
            [new TextDelta('Checking.'), new ToolCallComplete([$toolCall])],
            [new Text('Checking.'), $toolCall],
        ];

        yield 'completed web searches keep their replay signature' => [
            [
                new WebSearchComplete(new WebSearchResult(
                    'Symfony AI',
                    'ws_1',
                    'completed',
                    ['Symfony AI'],
                    'provider-signature',
                )),
            ],
            [new WebSearch('Symfony AI', 'ws_1', 'completed', ['Symfony AI'], 'provider-signature')],
        ];

        yield 'a mix of announced and unannounced calls keeps both' => [
            [
                new ToolCallStart('call_1', 'clock'),
                new TextDelta('and'),
                new ToolCallComplete([$toolCall, $other]),
            ],
            [$toolCall, new Text('and'), $other],
        ];

        yield 'an announced call that never completes leaves no hole' => [
            [
                new TextDelta('Before. '),
                new ToolCallStart('call_1', 'clock'),
                new ToolCallComplete([$other]),
            ],
            [new Text('Before. '), $other],
        ];

        yield 'thinking, text and a tool call in provider order' => [
            [
                new ThinkingStart(),
                new ThinkingDelta('I should check the clock.'),
                new ThinkingSignature('sig-xyz'),
                new ThinkingComplete('I should check the clock.', 'sig-xyz'),
                new TextDelta('Let me check that.'),
                new ToolCallStart('call_1', 'clock'),
                new ToolCallComplete([$toolCall]),
            ],
            [
                new Thinking('I should check the clock.', 'sig-xyz'),
                new Text('Let me check that.'),
                $toolCall,
            ],
        ];

        yield 'two calls sharing an id are both kept' => [
            [
                new ToolCallStart('call_1', 'clock'),
                new ToolCallStart('call_1', 'clock'),
                new ToolCallComplete([$toolCall, new ToolCall('call_1', 'clock', ['tz' => 'UTC'])]),
            ],
            [$toolCall, new ToolCall('call_1', 'clock', ['tz' => 'UTC'])],
        ];

        yield 'a call announced without an id is still kept' => [
            [
                new TextDelta('Before. '),
                new ToolCallStart('', 'clock'),
                new ToolCallComplete([$toolCall]),
            ],
            [new Text('Before. '), $toolCall],
        ];

        yield 'unrelated deltas are ignored' => [
            [new MetadataDelta('finish_reason', 'stop'), new TextDelta('Hi')],
            [new Text('Hi')],
        ];

        yield 'a stream with nothing replayable yields an empty message' => [
            [new ThinkingStart()],
            [],
        ];
    }

    public function testItResetsBetweenRuns()
    {
        $listener = new AssistantMessageStreamListener();
        $listener->accumulate(new TextDelta('First run'));

        $stream = new StreamResult((static function () {
            yield new TextDelta('Second run');
        })());
        $stream->addListener($listener);

        iterator_to_array($stream->getContent());

        $this->assertEquals([new Text('Second run')], $listener->getAssistantMessage()->getContent());
    }

    public function testItReadsTheTurnWhileTheStreamIsStillRunning()
    {
        $listener = new AssistantMessageStreamListener();
        $toolCall = new ToolCall('call_1', 'clock', []);

        $stream = new StreamResult((static function () use ($toolCall) {
            yield new TextDelta('Let me check.');
            yield new ToolCallComplete([$toolCall]);
            yield new TextDelta('Trailing.');
        })());
        $stream->addListener($listener);

        $seen = null;
        foreach ($stream->getContent() as $delta) {
            if ($delta instanceof ToolCallComplete) {
                // reading the turn mid-stream, before it has finished
                $seen = $listener->getAssistantMessage()->getContent();
            }
        }

        $this->assertEquals([new Text('Let me check.'), $toolCall], $seen);
    }

    public function testItIgnoresDeltasReplacedByAnotherListener()
    {
        $listener = new AssistantMessageStreamListener();

        $stream = new StreamResult((static function () {
            yield new TextDelta('Original');
        })());
        $stream->addListener(new class extends AbstractStreamListener {
            public function onDelta(DeltaEvent $event): void
            {
                $event->setDelta((static function () {
                    yield new TextDelta('Substituted');
                })());
            }
        });
        $stream->addListener($listener);

        iterator_to_array($stream->getContent(), false);

        $this->assertSame([], $listener->getAssistantMessage()->getContent());
    }
}

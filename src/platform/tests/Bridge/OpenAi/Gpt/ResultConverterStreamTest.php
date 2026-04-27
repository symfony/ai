<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Gpt;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Asserts phase-aware routing of OpenAI Responses streaming events.
 *
 * The OpenAI Responses API can emit both `commentary` and `final_answer` as
 * `response.output_text.delta` events. They can only be distinguished by the
 * item's phase tracked via `response.output_item.added` with a matching
 * `item_id`. The converter must not emit commentary as visible text, otherwise
 * it can duplicate output when `final_answer` repeats the same content.
 *
 * @author Pascal Cescon <pascal.cescon@gmail.com>
 */
final class ResultConverterStreamTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     */
    public function testCommentaryPhaseIsNotEmittedAsVisibleText()
    {
        $events = [
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_commentary_1', 'phase' => 'commentary'],
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_commentary_1',
                'delta' => 'Thinking about the problem...',
            ],
            [
                'type' => 'response.output_item.done',
                'item' => ['id' => 'item_commentary_1', 'phase' => 'commentary'],
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $this->assertSame([], self::extractTextDeltas($deltas), 'Commentary must never surface as TextDelta.');

        $thinkingStarts = array_filter($deltas, static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingStart);
        $this->assertCount(1, $thinkingStarts, 'Commentary should open exactly one ThinkingStart.');

        $thinkingDeltas = array_values(array_filter($deltas, static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingDelta));
        $this->assertCount(1, $thinkingDeltas);
        $this->assertSame('Thinking about the problem...', $thinkingDeltas[0]->getThinking());

        $thinkingCompletes = array_values(array_filter($deltas, static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes, 'Commentary must finalize on response.output_item.done.');
        $this->assertSame('Thinking about the problem...', $thinkingCompletes[0]->getThinking());
    }

    /**
     * @throws ExceptionInterface
     */
    public function testFinalAnswerPhaseStillEmitsTextDelta()
    {
        $events = [
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_final_1', 'phase' => 'final_answer'],
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_final_1',
                'delta' => 'Hello, ',
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_final_1',
                'delta' => 'world!',
            ],
            [
                'type' => 'response.output_item.done',
                'item' => ['id' => 'item_final_1', 'phase' => 'final_answer'],
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $this->assertSame(['Hello, ', 'world!'], self::extractTextDeltas($deltas));

        $thinkingDeltas = array_filter(
            $deltas,
            static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingDelta
                || $delta instanceof ThinkingStart
                || $delta instanceof ThinkingComplete,
        );
        $this->assertSame([], array_values($thinkingDeltas), 'Final answer must not emit thinking deltas.');
    }

    /**
     * @throws ExceptionInterface
     */
    public function testDeltaWithoutItemIdStillEmitsTextDelta()
    {
        $events = [
            [
                'type' => 'response.output_text.delta',
                'delta' => 'orphan delta',
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $this->assertSame(['orphan delta'], self::extractTextDeltas($deltas));
    }

    /**
     * @throws ExceptionInterface
     */
    public function testMultipleCommentaryDeltasAccumulateIntoThinkingComplete()
    {
        $events = [
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_c', 'phase' => 'commentary'],
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_c',
                'delta' => 'part 1 ',
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_c',
                'delta' => 'part 2 ',
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_c',
                'delta' => 'part 3',
            ],
            [
                'type' => 'response.output_item.done',
                'item' => ['id' => 'item_c', 'phase' => 'commentary'],
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $this->assertSame([], self::extractTextDeltas($deltas));

        $thinkingDeltas = array_values(array_filter($deltas, static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingDelta));
        $this->assertCount(3, $thinkingDeltas);

        $thinkingCompletes = array_values(array_filter($deltas, static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('part 1 part 2 part 3', $thinkingCompletes[0]->getThinking());
    }

    /**
     * A commentary item that is opened and closed without any delta in between
     * must not emit a stray ThinkingStart/Complete pair.
     *
     * @throws ExceptionInterface
     */
    public function testCommentaryItemDoneWithoutDeltasEmitsNothing()
    {
        $events = [
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_c', 'phase' => 'commentary'],
            ],
            [
                'type' => 'response.output_item.done',
                'item' => ['id' => 'item_c', 'phase' => 'commentary'],
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $this->assertSame([], $deltas, 'No deltas must be emitted for an empty commentary item.');
    }

    /**
     * Guards against regressing the long-standing reasoning_summary_text.* path.
     *
     * @throws ExceptionInterface
     */
    public function testReasoningSummaryStreamStillEmitsThinkingDeltas()
    {
        $events = [
            [
                'type' => 'response.reasoning_summary_text.delta',
                'delta' => 'reasoning step 1. ',
            ],
            [
                'type' => 'response.reasoning_summary_text.delta',
                'delta' => 'reasoning step 2.',
            ],
            [
                'type' => 'response.reasoning_summary_text.done',
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $this->assertSame([], self::extractTextDeltas($deltas));

        $types = array_map(static fn (DeltaInterface $delta): string => $delta::class, $deltas);
        $this->assertSame(
            [
                ThinkingStart::class,
                ThinkingDelta::class,
                ThinkingDelta::class,
                ThinkingComplete::class,
            ],
            $types,
        );

        $thinkingCompletes = array_values(array_filter($deltas, static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingComplete));
        $this->assertSame('reasoning step 1. reasoning step 2.', $thinkingCompletes[0]->getThinking());
    }

    /**
     * Mirrors the real-world bug: commentary and final_answer carry the same
     * content, but only final_answer should be surfaced as visible text.
     *
     * @throws ExceptionInterface
     */
    public function testInterleavedCommentaryAndFinalAnswerDoesNotDuplicateText()
    {
        $events = [
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_c', 'phase' => 'commentary'],
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_c',
                'delta' => 'The answer is 42.',
            ],
            [
                'type' => 'response.output_item.done',
                'item' => ['id' => 'item_c', 'phase' => 'commentary'],
            ],
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_f', 'phase' => 'final_answer'],
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_f',
                'delta' => 'The answer is 42.',
            ],
            [
                'type' => 'response.output_item.done',
                'item' => ['id' => 'item_f', 'phase' => 'final_answer'],
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $this->assertSame(['The answer is 42.'], self::extractTextDeltas($deltas), 'Visible text must appear exactly once.');
    }

    /**
     * Both items are opened first, then deltas alternate between them. Routing
     * must follow the item_id, not arrival order.
     *
     * @throws ExceptionInterface
     */
    public function testTrulyInterleavedCommentaryAndFinalAnswerAreRoutedByItemId()
    {
        $events = [
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_c', 'phase' => 'commentary'],
            ],
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_f', 'phase' => 'final_answer'],
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_c',
                'delta' => 'thinking...',
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_f',
                'delta' => 'visible',
            ],
            [
                'type' => 'response.output_item.done',
                'item' => ['id' => 'item_c', 'phase' => 'commentary'],
            ],
            [
                'type' => 'response.output_item.done',
                'item' => ['id' => 'item_f', 'phase' => 'final_answer'],
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $this->assertSame(['visible'], self::extractTextDeltas($deltas));

        $thinkingDeltas = array_values(array_filter($deltas, static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingDelta));
        $this->assertCount(1, $thinkingDeltas);
        $this->assertSame('thinking...', $thinkingDeltas[0]->getThinking());

        $thinkingCompletes = array_values(array_filter($deltas, static fn (DeltaInterface $delta): bool => $delta instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes, 'item_c.done must finalize commentary; item_f.done must not emit a second ThinkingComplete.');
        $this->assertSame('thinking...', $thinkingCompletes[0]->getThinking());
    }

    /**
     * Streams can be terminated (network abort, client disconnect) before the
     * `response.output_item.done` event arrives. Partial commentary must still
     * never surface as visible text, and ThinkingComplete must be absent so the
     * consumer can tell the thinking block was aborted rather than finalized.
     *
     * @throws ExceptionInterface
     */
    public function testCommentaryWithoutDoneDoesNotLeakVisibleText()
    {
        $events = [
            [
                'type' => 'response.output_item.added',
                'item' => ['id' => 'item_commentary_1', 'phase' => 'commentary'],
            ],
            [
                'type' => 'response.output_text.delta',
                'item_id' => 'item_commentary_1',
                'delta' => 'partial commentary',
            ],
        ];

        $deltas = self::collectDeltas(new ResultConverter(), $events);

        $types = array_map(static fn (DeltaInterface $delta): string => $delta::class, $deltas);
        $this->assertSame(
            [ThinkingStart::class, ThinkingDelta::class],
            $types,
            'Aborted commentary must emit ThinkingStart + ThinkingDelta but no TextDelta and no ThinkingComplete.',
        );
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @throws ExceptionInterface
     *
     * @return list<DeltaInterface>
     */
    private static function collectDeltas(ResultConverter $converter, array $events): array
    {
        $rawResult = new class(new MockResponse(), $events) implements RawResultInterface {
            /**
             * @param list<array<string, mixed>> $events
             */
            public function __construct(
                private readonly ResponseInterface $response,
                private readonly array $events,
            ) {
            }

            public function getObject(): ResponseInterface
            {
                return $this->response;
            }

            public function getData(): array
            {
                throw new \LogicException('getData() is not supported in stream tests.');
            }

            public function getDataStream(): \Generator
            {
                foreach ($this->events as $event) {
                    yield $event;
                }
            }
        };

        $result = $converter->convert($rawResult, ['stream' => true]);
        \assert($result instanceof StreamResult);

        $deltas = [];
        foreach ($result->getContent() as $delta) {
            \assert($delta instanceof DeltaInterface);
            $deltas[] = $delta;
        }

        return $deltas;
    }

    /**
     * @param list<DeltaInterface> $deltas
     *
     * @return list<string>
     */
    private static function extractTextDeltas(array $deltas): array
    {
        $texts = [];
        foreach ($deltas as $delta) {
            if ($delta instanceof TextDelta) {
                $texts[] = $delta->getText();
            }
        }

        return $texts;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests;

use Symfony\AI\Platform\Bridge\OpenResponses\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\CommentaryResult;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryComplete;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\CommentaryStart;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Test\Replay\AbstractBridgeReplayTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Replays recorded OpenAI Responses API interactions through the real bridge
 * pipeline. The cassettes freeze the provider's byte/event shape, so a
 * ResultConverter regression fails here instead of reaching a user.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReplayTest extends AbstractBridgeReplayTestCase
{
    public function testText()
    {
        $platform = $this->platformForCassette('text');

        $result = $platform->invoke('gpt-5-mini', new MessageBag(Message::ofUser('What is the purpose of an ant?')));

        $this->assertSame('The purpose of an ant is to serve its colony.', $result->asText());
    }

    public function testStreamingText()
    {
        $platform = $this->platformForCassette('streaming_text');

        $result = $platform->invoke('gpt-5-mini', new MessageBag(Message::ofUser('Greet the world.')), ['stream' => true]);

        $text = '';
        foreach ($result->asTextStream() as $delta) {
            $text .= $delta;
        }

        $this->assertSame('Hello world', $text);
    }

    public function testStreamingReasoning()
    {
        $platform = $this->platformForCassette('streaming_reasoning');

        $result = $platform->invoke('gpt-5-mini', new MessageBag(Message::ofUser('Why do ants exist?')), ['stream' => true]);

        $deltas = iterator_to_array($result->asStream(), false);

        $thinkingStart = array_filter($deltas, static fn ($delta): bool => $delta instanceof ThinkingStart);
        $thinkingComplete = array_filter($deltas, static fn ($delta): bool => $delta instanceof ThinkingComplete);

        $this->assertCount(1, $thinkingStart, 'reasoning stream opens exactly one thinking block');
        $this->assertCount(1, $thinkingComplete, 'reasoning stream closes exactly one thinking block');

        $thinking = '';
        foreach ($deltas as $delta) {
            if ($delta instanceof ThinkingDelta) {
                $thinking .= $delta->getThinking();
            }
        }
        $this->assertSame('Let me think about ants.', $thinking);

        $text = '';
        foreach ($deltas as $delta) {
            if ($delta instanceof TextDelta) {
                $text .= $delta->getText();
            }
        }
        $this->assertSame('Ants serve the colony.', $text);
    }

    public function testCommentaryIsReportedNextToTheAnswer()
    {
        $platform = $this->platformForCassette('commentary');

        $result = $platform->invoke('gpt-5.5', new MessageBag(
            Message::forSystem('Tell the user what you are about to do before you run code.'),
            Message::ofUser('Compute 17 * 25 by running Python code, then give me just the number.'),
        ), ['tools' => [['type' => 'code_interpreter', 'container' => ['type' => 'auto']]]]);

        $this->assertSame('425', $result->asText());

        $narration = array_values(array_filter(
            $result->asMultiPart(),
            static fn ($part): bool => $part instanceof CommentaryResult,
        ));

        $this->assertCount(1, $narration);
        $this->assertSame('I’ll run Python to compute the multiplication.', $narration[0]->getContent());
    }

    public function testStreamingCommentaryIsReportedNextToTheAnswer()
    {
        $platform = $this->platformForCassette('streaming_commentary');

        $result = $platform->invoke('gpt-5.5', new MessageBag(
            Message::forSystem('Tell the user what you are about to do before you run code.'),
            Message::ofUser('Compute 17 * 25 by running Python code, then give me just the number.'),
        ), ['tools' => [['type' => 'code_interpreter', 'container' => ['type' => 'auto']]], 'stream' => true]);

        $deltas = iterator_to_array($result->asStream(), false);

        $this->assertCount(1, array_filter($deltas, static fn ($delta): bool => $delta instanceof CommentaryStart));
        $this->assertCount(1, array_filter($deltas, static fn ($delta): bool => $delta instanceof CommentaryComplete));

        $commentary = '';
        $text = '';
        foreach ($deltas as $delta) {
            if ($delta instanceof CommentaryDelta) {
                $commentary .= $delta->getCommentary();
            }
            if ($delta instanceof TextDelta) {
                $text .= $delta->getText();
            }
        }

        $this->assertSame('I’m going to run Python to compute the multiplication.', $commentary);
        $this->assertSame('425', $text);
    }

    public function testToolCall()
    {
        $platform = $this->platformForCassette('tool_call');

        $result = $platform->invoke('gpt-5-mini', new MessageBag(Message::ofUser('What is the weather in Paris?')));

        $toolCalls = $result->asToolCalls();

        $this->assertCount(1, $toolCalls);
        $this->assertInstanceOf(ToolCall::class, $toolCalls[0]);
        $this->assertSame('call_1', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $toolCalls[0]->getArguments());
    }

    protected function createPlatform(HttpClientInterface $httpClient): PlatformInterface
    {
        return Factory::createPlatform('https://api.openai.com', 'test-api-key', $httpClient);
    }

    protected function cassetteDirectory(): string
    {
        return __DIR__.'/Fixtures/cassettes';
    }
}

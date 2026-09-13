<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class AssistantMessageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(Message::ofAssistant('Hello')));
        $this->assertFalse($normalizer->supportsNormalization('not a message'));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame(
            [\Symfony\AI\Platform\Message\AssistantMessage::class => true],
            (new AssistantMessageNormalizer())->getSupportedTypes(null),
        );
    }

    public function testATurnWithoutThinkingKeepsPlainStringContent()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertSame(
            ['role' => 'assistant', 'content' => 'It is 10:00.'],
            $normalizer->normalize(Message::ofAssistant('It is 10:00.')),
        );
    }

    public function testThinkingIsReplayedAsAThinkChunk()
    {
        $normalizer = new AssistantMessageNormalizer();

        $message = Message::ofAssistant(new Thinking('Let me check the clock.'), new Text('It is 10:00.'));

        // Mistral rejects `reasoning_content` with a 422 and reads the reasoning back from the
        // content list instead, which is also the shape its ResultConverter parses
        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Let me check the clock.']]],
                ['type' => 'text', 'text' => 'It is 10:00.'],
            ],
        ], $normalizer->normalize($message));
    }

    public function testThinkingWithoutTextOmitsTheTextChunk()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Still thinking.']]],
            ],
        ], $normalizer->normalize(Message::ofAssistant(new Thinking('Still thinking.'))));
    }

    public function testChunksKeepTheOrderTheModelProducedThemIn()
    {
        $normalizer = new AssistantMessageNormalizer();

        $message = Message::ofAssistant(
            new Text('Let me check. '),
            new Thinking('The clock tool answers this.'),
            new Text('It is 10:00.'),
        );

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'Let me check. '],
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'The clock tool answers this.']]],
                ['type' => 'text', 'text' => 'It is 10:00.'],
            ],
        ], $normalizer->normalize($message));
    }

    public function testConsecutivePartsOfOneKindBecomeOneChunk()
    {
        $normalizer = new AssistantMessageNormalizer();

        $message = Message::ofAssistant(
            new Thinking('First. '),
            new Thinking('Second.'),
            new Text('It is '),
            new Text('10:00.'),
        );

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'First. Second.']]],
                ['type' => 'text', 'text' => 'It is 10:00.'],
            ],
        ], $normalizer->normalize($message));
    }

    public function testAnEmptyThinkingBlockIsNotReplayed()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertSame(
            ['role' => 'assistant', 'content' => 'It is 10:00.'],
            $normalizer->normalize(Message::ofAssistant(new Thinking(''), new Text('It is 10:00.'))),
        );
    }

    public function testToolCallsAreNormalizedAlongsideTheThinkChunk()
    {
        $toolCall = new ToolCall('call_1', 'clock');

        $inner = $this->createMock(NormalizerInterface::class);
        $inner->method('normalize')->willReturn([['id' => 'call_1']]);

        $normalizer = new AssistantMessageNormalizer();
        $normalizer->setNormalizer($inner);

        $actual = $normalizer->normalize(Message::ofAssistant(new Thinking('Which tool?'), $toolCall));

        $this->assertSame([
            ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Which tool?']]],
        ], $actual['content']);
        $this->assertSame([['id' => 'call_1']], $actual['tool_calls']);
    }
}

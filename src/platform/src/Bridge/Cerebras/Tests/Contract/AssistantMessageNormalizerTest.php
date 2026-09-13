<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cerebras\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
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
            [AssistantMessage::class => true],
            (new AssistantMessageNormalizer())->getSupportedTypes(null),
        );
    }

    public function testThinkingIsDropped()
    {
        $message = Message::ofAssistant(new Thinking('Let me think.'), new Text('It is 10:00.'));

        // Cerebras answers `reasoning_content` with a 400, and accepts no other shape for it
        $this->assertSame(
            ['role' => 'assistant', 'content' => 'It is 10:00.'],
            (new AssistantMessageNormalizer())->normalize($message),
        );
    }

    public function testToolCallsSurviveAlongsideTheDroppedThinking()
    {
        $inner = $this->createMock(NormalizerInterface::class);
        $inner->method('normalize')->willReturn([['id' => 'call_1']]);

        $normalizer = new AssistantMessageNormalizer();
        $normalizer->setNormalizer($inner);

        $actual = $normalizer->normalize(
            Message::ofAssistant(new Thinking('Which tool?'), new ToolCall('call_1', 'clock')),
        );

        $this->assertArrayNotHasKey('reasoning_content', $actual);
        $this->assertSame([['id' => 'call_1']], $actual['tool_calls']);
    }
}

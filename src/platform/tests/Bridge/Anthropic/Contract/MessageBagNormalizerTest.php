<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MessageBagNormalizerTest extends TestCase
{
    private MessageBagNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MessageBagNormalizer();

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->normalizer->setNormalizer($innerNormalizer);
    }

    public function testSupportsNormalization()
    {
        $context = [Contract::CONTEXT_MODEL => new Claude(Claude::SONNET_4)];

        $this->assertTrue($this->normalizer->supportsNormalization(new MessageBag(), null, $context));
        $this->assertFalse($this->normalizer->supportsNormalization(new MessageBag(), null, []));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass(), null, $context));
    }

    public function testNormalizeEmitsSystemMessageAsContentBlockArray()
    {
        $messageBag = new MessageBag(
            new SystemMessage('You are a helpful assistant.'),
            new UserMessage(new Text('Hello')),
        );

        $result = $this->normalizer->normalize($messageBag, context: [
            Contract::CONTEXT_MODEL => new Claude(Claude::SONNET_4),
        ]);

        $this->assertSame([
            ['type' => 'text', 'text' => 'You are a helpful assistant.'],
        ], $result['system']);
    }

    public function testNormalizeWithoutSystemMessageHasNoSystemKey()
    {
        $messageBag = new MessageBag(
            new UserMessage(new Text('Hello')),
        );

        $result = $this->normalizer->normalize($messageBag, context: [
            Contract::CONTEXT_MODEL => new Claude(Claude::SONNET_4),
        ]);

        $this->assertArrayNotHasKey('system', $result);
    }
}

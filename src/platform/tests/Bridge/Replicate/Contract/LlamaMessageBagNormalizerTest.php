<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Replicate\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\Replicate\Contract\LlamaMessageBagNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class LlamaMessageBagNormalizerTest extends TestCase
{
    public function testNormalizeWithSystemMessage()
    {
        $normalizer = new LlamaMessageBagNormalizer();
        $messageBag = new MessageBag(
            Message::forSystem('You are helpful'),
            Message::ofUser('Hello'),
            Message::ofAssistant('Hi there')
        );

        $result = $normalizer->normalize($messageBag, null, ['model' => new Llama('llama-3.1-405b-instruct')]);

        $this->assertArrayHasKey('system', $result);
        $this->assertArrayHasKey('prompt', $result);
        $this->assertStringContainsString('You are helpful', $result['system']);
        $this->assertStringContainsString('Hello', $result['prompt']);
        $this->assertStringContainsString('Hi there', $result['prompt']);
    }

    public function testNormalizeWithoutSystemMessage()
    {
        $normalizer = new LlamaMessageBagNormalizer();
        $messageBag = new MessageBag(
            Message::ofUser('Hello'),
            Message::ofAssistant('Hi there')
        );

        $result = $normalizer->normalize($messageBag, null, ['model' => new Llama('llama-3.1-405b-instruct')]);

        $this->assertArrayHasKey('system', $result);
        $this->assertArrayHasKey('prompt', $result);
        $this->assertStringContainsString('system', $result['system']);
        $this->assertStringContainsString('Hello', $result['prompt']);
        $this->assertStringContainsString('Hi there', $result['prompt']);
    }

    public function testSupportsLlamaModel()
    {
        $normalizer = new LlamaMessageBagNormalizer();
        $messageBag = new MessageBag(Message::ofUser('Hello'));

        $this->assertTrue($normalizer->supportsNormalization($messageBag, null, ['model' => new Llama('llama-3.1-405b-instruct')]));
    }

    public function testDoesNotSupportOtherModels()
    {
        $normalizer = new LlamaMessageBagNormalizer();
        $messageBag = new MessageBag(Message::ofUser('Hello'));
        $otherModel = $this->createMock(Model::class);

        $this->assertFalse($normalizer->supportsNormalization($messageBag, null, ['model' => $otherModel]));
    }
}

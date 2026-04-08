<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MessageBagNormalizerTest extends TestCase
{
    public function testSupportsCodexModel()
    {
        $normalizer = new MessageBagNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(
            new MessageBag(),
            context: [Contract::CONTEXT_MODEL => new Codex('gpt-5-codex')],
        ));
    }

    public function testDoesNotSupportOtherModels()
    {
        $normalizer = new MessageBagNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(
            new MessageBag(),
            context: [Contract::CONTEXT_MODEL => new Model('sonnet')],
        ));
    }

    public function testDoesNotSupportNonMessageBag()
    {
        $normalizer = new MessageBagNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(
            'not a message bag',
            context: [Contract::CONTEXT_MODEL => new Codex('gpt-5-codex')],
        ));
    }

    public function testNormalizeExtractsPromptAndSystemPrompt()
    {
        $messageBag = new MessageBag(
            Message::forSystem('You are a pirate.'),
            Message::ofUser('What is PHP?'),
        );

        $result = $this->normalize($messageBag);

        $this->assertSame('What is PHP?', $result['prompt']);
        $this->assertSame('You are a pirate.', $result['system_prompt']);
    }

    public function testNormalizeWithoutSystemMessage()
    {
        $messageBag = new MessageBag(
            Message::ofUser('Hello'),
        );

        $result = $this->normalize($messageBag);

        $this->assertSame('Hello', $result['prompt']);
        $this->assertArrayNotHasKey('system_prompt', $result);
    }

    public function testNormalizeUsesLastUserMessage()
    {
        $messageBag = new MessageBag(
            Message::ofUser('First question'),
            Message::ofUser('Second question'),
        );

        $result = $this->normalize($messageBag);

        $this->assertSame('Second question', $result['prompt']);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(MessageBag $messageBag): array
    {
        $contract = Contract::create(new MessageBagNormalizer());
        $model = new Codex('gpt-5-codex');

        return $contract->createRequestPayload($model, $messageBag);
    }
}

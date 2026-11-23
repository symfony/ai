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
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
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

    public function testNormalizeJoinsUserMessagesAfterLastAssistantMessage()
    {
        $messageBag = new MessageBag(
            Message::ofUser('Earlier question'),
            Message::ofAssistant('Earlier answer'),
            Message::ofUser('The task: FIRST'),
            Message::ofUser('The diff: SECOND'),
        );

        $result = $this->normalize($messageBag);

        $this->assertSame("The task: FIRST\n\nThe diff: SECOND", $result['prompt']);
    }

    public function testNormalizeJoinsTextBlocksOfUserMessage()
    {
        $messageBag = new MessageBag(
            Message::ofUser('The task: FIRST', 'The diff: SECOND'),
        );

        $result = $this->normalize($messageBag);

        $this->assertSame("The task: FIRST\n\nThe diff: SECOND", $result['prompt']);
    }

    public function testNormalizeUsesLastUserMessageWhenBagEndsWithAssistantMessage()
    {
        $messageBag = new MessageBag(
            Message::ofUser('Earlier question'),
            Message::ofAssistant('Earlier answer'),
            Message::ofUser('The task: FIRST', 'The diff: SECOND'),
            Message::ofAssistant('Answer'),
        );

        $result = $this->normalize($messageBag);

        $this->assertSame("The task: FIRST\n\nThe diff: SECOND", $result['prompt']);
    }

    public function testNormalizeThrowsOnImageContent()
    {
        $messageBag = new MessageBag(
            Message::ofUser('What is in this image?', new Image('fake-image-data', 'image/png')),
        );

        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage('Model "gpt-5-codex" (Symfony\AI\Platform\Bridge\Codex\Codex) does not support "image input".');

        $this->normalize($messageBag);
    }

    public function testNormalizeThrowsOnImageUrlContent()
    {
        $messageBag = new MessageBag(
            Message::ofUser('What is in this image?', new ImageUrl('https://example.com/image.png')),
        );

        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage('Model "gpt-5-codex" (Symfony\AI\Platform\Bridge\Codex\Codex) does not support "image input".');

        $this->normalize($messageBag);
    }

    public function testNormalizeThrowsOnImageOnlyUserMessage()
    {
        $messageBag = new MessageBag(
            Message::ofUser(new Image('fake-image-data', 'image/png')),
        );

        $this->expectException(MissingModelSupportException::class);

        $this->normalize($messageBag);
    }

    public function testNormalizeThrowsOnAudioContent()
    {
        $messageBag = new MessageBag(
            Message::ofUser('Transcribe this', new Audio('fake-audio-data', 'audio/mp3')),
        );

        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage('Model "gpt-5-codex" (Symfony\AI\Platform\Bridge\Codex\Codex) does not support "audio input".');

        $this->normalize($messageBag);
    }

    public function testNormalizeIgnoresImageInEarlierTurnBeforeAssistantMessage()
    {
        $messageBag = new MessageBag(
            Message::ofUser('Earlier question', new Image('fake-image-data', 'image/png')),
            Message::ofAssistant('Earlier answer'),
            Message::ofUser('Follow-up question'),
        );

        $result = $this->normalize($messageBag);

        $this->assertSame('Follow-up question', $result['prompt']);
    }

    public function testNormalizeThrowsWhenFallbackUserMessageContainsImage()
    {
        $messageBag = new MessageBag(
            Message::ofUser('What is in this image?', new Image('fake-image-data', 'image/png')),
            Message::ofAssistant('Answer'),
        );

        $this->expectException(MissingModelSupportException::class);

        $this->normalize($messageBag);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(MessageBag $messageBag): array
    {
        $contract = Contract::create([new MessageBagNormalizer()]);
        $model = new Codex('gpt-5-codex');

        return $contract->createRequestPayload($model, $messageBag);
    }
}

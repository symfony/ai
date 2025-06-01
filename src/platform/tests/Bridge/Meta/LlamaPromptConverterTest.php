<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Meta;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Meta\LlamaPromptConverter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;

#[CoversClass(LlamaPromptConverter::class)]
#[Small]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(ImageUrl::class)]
#[UsesClass(Text::class)]
#[UsesClass(Message::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
final class LlamaPromptConverterTest extends TestCase
{
    #[Test]
    public function convertMessages(): void
    {
        $messageBag = new MessageBag();
        foreach (self::provideMessages() as $message) {
            $messageBag->add($message[1]);
        }

        self::assertSame(<<<EXPECTED
            <|begin_of_text|><|start_header_id|>system<|end_header_id|>

            You are a helpful chatbot.<|eot_id|>

            <|start_header_id|>user<|end_header_id|>

            Hello, how are you?<|eot_id|>

            <|start_header_id|>user<|end_header_id|>

            Hello, how are you?
            What is your name?<|eot_id|>

            <|start_header_id|>user<|end_header_id|>

            Hello, how are you?
            What is your name?
            https://example.com/image.jpg<|eot_id|>

            <|start_header_id|>assistant<|end_header_id|>

            I am an assistant.<|eot_id|>

            <|start_header_id|>assistant<|end_header_id|>
            EXPECTED,
            (new LlamaPromptConverter())->convertToPrompt($messageBag)
        );
    }

    #[Test]
    #[DataProvider('provideMessages')]
    public function convertMessage(string $expected, UserMessage|SystemMessage|AssistantMessage $message): void
    {
        self::assertSame(
            $expected,
            (new LlamaPromptConverter())->convertMessage($message)
        );
    }

    /**
     * @return iterable<array{string, UserMessage|SystemMessage|AssistantMessage}>
     */
    public static function provideMessages(): iterable
    {
        yield 'System message' => [
            <<<SYSTEM
                <|begin_of_text|><|start_header_id|>system<|end_header_id|>

                You are a helpful chatbot.<|eot_id|>
                SYSTEM,
            Message::forSystem('You are a helpful chatbot.'),
        ];

        yield 'UserMessage' => [
            <<<USER
                <|start_header_id|>user<|end_header_id|>

                Hello, how are you?<|eot_id|>
                USER,
            Message::ofUser('Hello, how are you?'),
        ];

        yield 'UserMessage with two texts' => [
            <<<USER
                <|start_header_id|>user<|end_header_id|>

                Hello, how are you?
                What is your name?<|eot_id|>
                USER,
            Message::ofUser('Hello, how are you?', 'What is your name?'),
        ];

        yield 'UserMessage with two texts and one image' => [
            <<<USER
                <|start_header_id|>user<|end_header_id|>

                Hello, how are you?
                What is your name?
                https://example.com/image.jpg<|eot_id|>
                USER,
            Message::ofUser('Hello, how are you?', 'What is your name?', new ImageUrl('https://example.com/image.jpg')),
        ];

        yield 'AssistantMessage' => [
            <<<ASSISTANT
                <|start_header_id|>assistant<|end_header_id|>

                I am an assistant.<|eot_id|>
                ASSISTANT,
            new AssistantMessage('I am an assistant.'),
        ];

        yield 'AssistantMessage with null content' => [
            '',
            new AssistantMessage(),
        ];
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Compression;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Compression\SummarizationStrategy;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Test\InMemoryPlatform;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SummarizationStrategyTest extends TestCase
{
    public function testShouldCompressReturnsTrueWhenExceedingThreshold()
    {
        $platform = new InMemoryPlatform('summary');
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 3);

        $messages = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
            Message::ofUser('Message 2'),
            Message::ofAssistant('Response 2'),
        );

        $this->assertTrue($strategy->shouldCompress($messages));
    }

    public function testShouldCompressReturnsFalseWhenBelowThreshold()
    {
        $platform = new InMemoryPlatform('summary');
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 10);

        $messages = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );

        $this->assertFalse($strategy->shouldCompress($messages));
    }

    public function testShouldCompressExcludesSystemMessagesFromCount()
    {
        $platform = new InMemoryPlatform('summary');
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 3);

        $messages = new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
            Message::ofUser('Message 2'),
        );

        $this->assertFalse($strategy->shouldCompress($messages));
    }

    public function testCompressReturnsOriginalWhenNothingToSummarize()
    {
        $platform = new InMemoryPlatform('summary');
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 3, keepRecent: 6);

        $messages = new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );

        $result = $strategy->compress($messages);

        $this->assertSame($messages, $result);
    }

    public function testCompressSummarizesOldMessagesAndKeepsRecent()
    {
        $platform = new InMemoryPlatform('This is the summary of the conversation.');
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 3, keepRecent: 2);

        $messages = new MessageBag(
            Message::forSystem('Original system prompt'),
            Message::ofUser('Old message 1'),
            Message::ofAssistant('Old response 1'),
            Message::ofUser('Recent message'),
            Message::ofAssistant('Recent response'),
        );

        $result = $strategy->compress($messages);
        $resultMessages = $result->getMessages();

        $this->assertCount(3, $resultMessages);

        $systemMessage = $result->getSystemMessage();
        $this->assertNotNull($systemMessage);
        $this->assertStringContainsString('Original system prompt', $systemMessage->getContent());
        $this->assertStringContainsString('# Previous Conversation Summary', $systemMessage->getContent());
        $this->assertStringContainsString('This is the summary of the conversation.', $systemMessage->getContent());

        $this->assertInstanceOf(UserMessage::class, $resultMessages[1]);
        $this->assertSame('Recent message', $resultMessages[1]->asText());

        $this->assertInstanceOf(AssistantMessage::class, $resultMessages[2]);
        $this->assertSame('Recent response', $resultMessages[2]->getContent());
    }

    public function testCompressCreatesSystemMessageWhenNoneExists()
    {
        $platform = new InMemoryPlatform('Summary without system message.');
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 2, keepRecent: 2);

        $messages = new MessageBag(
            Message::ofUser('Old message'),
            Message::ofAssistant('Old response'),
            Message::ofUser('Recent message'),
            Message::ofAssistant('Recent response'),
        );

        $result = $strategy->compress($messages);

        $systemMessage = $result->getSystemMessage();
        $this->assertNotNull($systemMessage);
        $this->assertStringContainsString('# Previous Conversation Summary', $systemMessage->getContent());
        $this->assertStringContainsString('Summary without system message.', $systemMessage->getContent());
        $this->assertStringNotContainsString("\n\n# Previous", $systemMessage->getContent());
    }

    public function testCompressFormatsUserMessages()
    {
        $capturedOptions = null;
        $platform = new InMemoryPlatform(static function ($model, $input, $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return 'Summary';
        });
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 1, keepRecent: 1);

        $messages = new MessageBag(
            Message::ofUser('Hello, how are you?'),
            Message::ofUser('Recent message'),
        );

        $strategy->compress($messages);

        $this->assertNotNull($capturedOptions);
        $this->assertCount(1, $capturedOptions);
        $this->assertArrayHasKey('template_vars', $capturedOptions);
        $this->assertArrayHasKey('conversation', $capturedOptions['template_vars']);
        $this->assertStringContainsString('User: Hello, how are you?', $capturedOptions['template_vars']['conversation']);
    }

    public function testCompressFormatsAssistantMessages()
    {
        $capturedOptions = null;
        $platform = new InMemoryPlatform(static function ($model, $input, $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return 'Summary';
        });
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 1, keepRecent: 1);

        $messages = new MessageBag(
            Message::ofAssistant('I am doing well, thank you!'),
            Message::ofUser('Recent message'),
        );

        $strategy->compress($messages);

        $this->assertNotNull($capturedOptions);
        $this->assertCount(1, $capturedOptions);
        $this->assertArrayHasKey('template_vars', $capturedOptions);
        $this->assertArrayHasKey('conversation', $capturedOptions['template_vars']);
        $this->assertStringContainsString('Assistant: I am doing well, thank you!', $capturedOptions['template_vars']['conversation']);
    }

    public function testCompressFormatsToolCallMessages()
    {
        $capturedOptions = null;
        $platform = new InMemoryPlatform(static function ($model, $input, $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return 'Summary';
        });
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 1, keepRecent: 1);

        $toolCall = new ToolCall('call-123', 'search_tool', ['query' => 'test']);
        $toolCallMessage = new ToolCallMessage($toolCall, 'Search results: found 5 items');

        $messages = new MessageBag(
            $toolCallMessage,
            Message::ofUser('Recent message'),
        );

        $strategy->compress($messages);

        $this->assertNotNull($capturedOptions);
        $this->assertCount(1, $capturedOptions);
        $this->assertArrayHasKey('template_vars', $capturedOptions);
        $this->assertArrayHasKey('conversation', $capturedOptions['template_vars']);
        $this->assertStringContainsString('Tool: [search_tool] Search results: found 5 items', $capturedOptions['template_vars']['conversation']);
    }

    public function testCompressTruncatesLongToolCallResults()
    {
        $capturedOptions = null;
        $platform = new InMemoryPlatform(static function ($model, $input, $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return 'Summary';
        });
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 1, keepRecent: 1);

        $longContent = str_repeat('a', 300);
        $toolCall = new ToolCall('call-123', 'long_tool', []);
        $toolCallMessage = new ToolCallMessage($toolCall, $longContent);

        $messages = new MessageBag(
            $toolCallMessage,
            Message::ofUser('Recent message'),
        );

        $strategy->compress($messages);

        $this->assertNotNull($capturedOptions);
        $this->assertCount(1, $capturedOptions);
        $this->assertArrayHasKey('template_vars', $capturedOptions);
        $this->assertArrayHasKey('conversation', $capturedOptions['template_vars']);
        $this->assertStringContainsString('[long_tool] '.str_repeat('a', 200).'...', $capturedOptions['template_vars']['conversation']);
    }

    public function testCompressUsesCorrectModel()
    {
        $capturedModel = null;
        $platform = new InMemoryPlatform(static function ($model, $input) use (&$capturedModel) {
            $capturedModel = $model->getName();

            return 'Summary';
        });
        $strategy = new SummarizationStrategy($platform, 'custom-summary-model', threshold: 1, keepRecent: 1);

        $messages = new MessageBag(
            Message::ofUser('Old message'),
            Message::ofUser('Recent message'),
        );

        $strategy->compress($messages);

        $this->assertSame('custom-summary-model', $capturedModel);
    }

    public function testCompressSkipsEmptyMessages()
    {
        $capturedOptions = null;
        $platform = new InMemoryPlatform(static function ($model, $input, $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return 'Summary';
        });
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 1, keepRecent: 1);

        $messages = new MessageBag(
            Message::ofUser('Valid message'),
            Message::ofAssistant(''),
            Message::ofUser('Recent message'),
        );

        $strategy->compress($messages);

        $this->assertNotNull($capturedOptions);
        $this->assertCount(1, $capturedOptions);
        $this->assertArrayHasKey('template_vars', $capturedOptions);
        $this->assertArrayHasKey('conversation', $capturedOptions['template_vars']);
        $this->assertStringContainsString('User: Valid message', $capturedOptions['template_vars']['conversation']);
        $this->assertStringNotContainsString('Assistant:', $capturedOptions['template_vars']['conversation']);
    }

    public function testCompressWithDefaultThresholdAndKeepRecent()
    {
        $platform = new InMemoryPlatform('summary');
        $strategy = new SummarizationStrategy($platform, 'test-model');

        $messages = new MessageBag();
        for ($i = 0; $i < 15; ++$i) {
            $messages = $messages->with(Message::ofUser("Message {$i}"));
        }

        $this->assertFalse($strategy->shouldCompress($messages));

        for ($i = 15; $i < 25; ++$i) {
            $messages = $messages->with(Message::ofUser("Message {$i}"));
        }

        $this->assertTrue($strategy->shouldCompress($messages));
    }

    public function testCompressReturnsOriginalWhenConversationTextIsEmpty()
    {
        $apiCalled = false;
        $platform = new InMemoryPlatform(static function () use (&$apiCalled) {
            $apiCalled = true;

            return 'Summary';
        });
        $strategy = new SummarizationStrategy($platform, 'test-model', threshold: 1, keepRecent: 1);

        $messages = new MessageBag(
            Message::ofAssistant(''),
            Message::ofAssistant(''),
            Message::ofUser('Recent message'),
        );

        $result = $strategy->compress($messages);

        $this->assertSame($messages, $result);
        $this->assertFalse($apiCalled, 'API should not be called when conversation text is empty');
    }
}

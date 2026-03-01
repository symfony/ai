<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AnthropicContract;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\PromptCacheNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PromptCacheNormalizerTest extends TestCase
{
    private Contract $contract;
    private Claude $model;

    protected function setUp(): void
    {
        $this->contract = AnthropicContract::create();
        $this->model = new Claude('claude-3-5-sonnet-latest');
    }

    public function testSupportsNormalizationForMessageBagAndClaude()
    {
        $normalizer = new PromptCacheNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new MessageBag(), context: [
            Contract::CONTEXT_MODEL => new Claude('claude-3-5-sonnet-latest'),
        ]));
    }

    public function testDoesNotSupportNonMessageBag()
    {
        $normalizer = new PromptCacheNormalizer();

        $this->assertFalse($normalizer->supportsNormalization('not a message bag'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new PromptCacheNormalizer();

        $this->assertSame([MessageBag::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNoInjectionWhenCacheRetentionIsAbsent()
    {
        $bag = new MessageBag(Message::ofUser('Hello'));

        $result = $this->contract->createRequestPayload($this->model, $bag);

        $messages = $result['messages'];
        $this->assertSame('Hello', $messages[0]['content']);
    }

    public function testNoInjectionWhenCacheRetentionIsNone()
    {
        $bag = new MessageBag(Message::ofUser('Hello'));

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'none']);

        $messages = $result['messages'];
        // Content must remain a plain string – no block promotion, no annotation.
        $this->assertSame('Hello', $messages[0]['content']);
        $this->assertIsString($messages[0]['content']);
    }

    public function testShortRetentionInjectsEphemeralOnPlainStringContent()
    {
        $bag = new MessageBag(Message::ofUser('Hello world'));

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'short']);

        $messages = $result['messages'];
        // Plain string must have been promoted to a block
        $this->assertIsArray($messages[0]['content']);
        $this->assertSame('text', $messages[0]['content'][0]['type']);
        $this->assertSame('Hello world', $messages[0]['content'][0]['text']);
        $this->assertSame(['type' => 'ephemeral'], $messages[0]['content'][0]['cache_control']);
    }

    public function testShortRetentionInjectsEphemeralOnLastBlockOfMultiBlockMessage()
    {
        $bag = new MessageBag(new \Symfony\AI\Platform\Message\UserMessage(
            new Text('First block'),
            new Text('Second block'),
        ));

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'short']);

        $messages = $result['messages'];
        $content = $messages[0]['content'];
        $this->assertCount(2, $content);
        // Only the LAST block carries the annotation
        $this->assertArrayNotHasKey('cache_control', $content[0]);
        $this->assertSame(['type' => 'ephemeral'], $content[1]['cache_control']);
    }

    public function testLongRetentionInjectsEphemeralWithTtl()
    {
        $bag = new MessageBag(Message::ofUser('Cache for an hour'));

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'long']);

        $messages = $result['messages'];
        $this->assertSame(
            ['type' => 'ephemeral', 'ttl' => '1h'],
            $messages[0]['content'][0]['cache_control'],
        );
    }

    public function testAnnotationIsPlacedOnLastUserMessageNotPrevious()
    {
        $bag = new MessageBag(
            Message::ofUser('First user message'),
            new AssistantMessage('Acknowledged'),
            Message::ofUser('Second user message'),
        );

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'short']);

        $messages = $result['messages'];
        // Three messages: user, assistant, user
        $this->assertCount(3, $messages);

        $firstUser = $messages[0];
        $assistant = $messages[1];
        $secondUser = $messages[2];

        // First user message must NOT have cache_control
        $this->assertSame('First user message', $firstUser['content']);

        // Assistant message is unchanged
        $this->assertSame('Acknowledged', $assistant['content']);

        // Second (last) user message carries the annotation
        $this->assertIsArray($secondUser['content']);
        $this->assertSame(['type' => 'ephemeral'], $secondUser['content'][0]['cache_control']);
    }

    public function testAnnotationIsInjectedOnToolResultBlock()
    {
        $bag = new MessageBag(
            Message::ofUser('Run the tool please'),
            new AssistantMessage(toolCalls: [new ToolCall('call_1', 'my_tool', ['arg' => 'val'])]),
            new ToolCallMessage(new ToolCall('call_1', 'my_tool'), 'tool output'),
        );

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'short']);

        $messages = $result['messages'];
        // Last message is the tool-result user message
        $lastMessage = end($messages);
        $this->assertSame('user', $lastMessage['role']);
        $lastBlock = end($lastMessage['content']);
        $this->assertSame('tool_result', $lastBlock['type']);
        $this->assertSame(['type' => 'ephemeral'], $lastBlock['cache_control']);
    }

    public function testNoAnnotationWhenMessageBagHasNoUserMessages()
    {
        // MessageBag with only a system message (which is stripped from 'messages')
        $bag = new MessageBag(Message::forSystem('You are helpful'));

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'short']);

        // messages array is empty – nothing to annotate, no exception
        $this->assertSame([], $result['messages']);
    }

    public function testSystemPromptAndModelArePassedThrough()
    {
        $bag = new MessageBag(
            Message::forSystem('System prompt here'),
            Message::ofUser('Hello'),
        );

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'short']);

        $this->assertSame('System prompt here', $result['system']);
        $this->assertSame('claude-3-5-sonnet-latest', $result['model']);
    }

    public function testUnknownRetentionValueFallsBackToEphemeral()
    {
        $bag = new MessageBag(Message::ofUser('Hello'));

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => 'custom_value']);

        $messages = $result['messages'];
        $this->assertSame(['type' => 'ephemeral'], $messages[0]['content'][0]['cache_control']);
    }

    /**
     * @param array{type: string, ttl?: string} $expectedCacheControl
     */
    #[DataProvider('cacheRetentionProvider')]
    public function testCacheControlShapeForRetentionValue(string $retention, array $expectedCacheControl)
    {
        $bag = new MessageBag(Message::ofUser('Test'));

        $result = $this->contract->createRequestPayload($this->model, $bag, ['cacheRetention' => $retention]);

        $messages = $result['messages'];
        $this->assertSame($expectedCacheControl, $messages[0]['content'][0]['cache_control']);
    }

    /**
     * @return iterable<string, array{0: string, 1: array{type: string, ttl?: string}}>
     */
    public static function cacheRetentionProvider(): iterable
    {
        yield 'short' => ['short', ['type' => 'ephemeral']];
        yield 'long' => ['long',  ['type' => 'ephemeral', 'ttl' => '1h']];
    }
}

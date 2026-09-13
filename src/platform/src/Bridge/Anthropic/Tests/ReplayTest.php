<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Test\Replay\AbstractBridgeReplayTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Replays recorded Anthropic interactions through the real bridge pipeline.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReplayTest extends AbstractBridgeReplayTestCase
{
    /**
     * Pins the event vocabulary and ordering the provider emits, which synthetic deltas cannot.
     */
    public function testStreamedThinkingAndToolCallRebuildTheAssistantTurn()
    {
        $platform = $this->platformForCassette('streaming_thinking_tool_call');

        $result = $platform->invoke(
            'claude-sonnet-4-5-20250929',
            new MessageBag(Message::ofUser('What time is it right now?')),
            [
                'stream' => true,
                'max_tokens' => 4000,
                'thinking' => ['type' => 'enabled', 'budget_tokens' => 1024],
            ],
        )->getResult();

        $this->assertInstanceOf(StreamResult::class, $result);

        $content = Message::ofAssistant($result)->getContent();

        $this->assertCount(2, $content);

        $this->assertInstanceOf(Thinking::class, $content[0]);
        $this->assertNotSame('', $content[0]->getContent());
        $this->assertNotNull($content[0]->getSignature(), 'the signature Anthropic validates is preserved');

        $this->assertInstanceOf(ToolCall::class, $content[1]);
        $this->assertSame('clock', $content[1]->getName());
    }

    protected function createPlatform(HttpClientInterface $httpClient): PlatformInterface
    {
        return Factory::createPlatform('test-api-key', httpClient: $httpClient);
    }

    protected function cassetteDirectory(): string
    {
        return __DIR__.'/Fixtures/cassettes';
    }
}

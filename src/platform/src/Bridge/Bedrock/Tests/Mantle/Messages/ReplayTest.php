<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Mantle\Messages;

use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Test\Replay\AbstractBridgeReplayTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Replays raw Anthropic Messages responses from the Bedrock Mantle endpoint through the complete
 * bridge pipeline, including its SSE framing.
 *
 * @author asrar <aszenz@gmail.com>
 */
final class ReplayTest extends AbstractBridgeReplayTestCase
{
    public function testText()
    {
        $platform = $this->platformForCassette('text');

        $result = $platform->invoke('anthropic.claude-haiku-4-5', new MessageBag(Message::ofUser('Greet the world.')));

        $this->assertSame('Hello from Bedrock Mantle.', $result->asText());
    }

    public function testStreamingText()
    {
        $platform = $this->platformForCassette('streaming_text');
        $result = $platform->invoke('anthropic.claude-haiku-4-5', new MessageBag(Message::ofUser('Greet the world.')), ['stream' => true]);

        $text = '';
        foreach ($result->asTextStream() as $delta) {
            $text .= $delta;
        }

        $this->assertSame('Hello from Bedrock Mantle.', $text);
    }

    public function testToolCall()
    {
        $platform = $this->platformForCassette('tool_call');
        $result = $platform->invoke('anthropic.claude-haiku-4-5', new MessageBag(Message::ofUser('What is the weather in Paris?')));

        $toolCalls = $result->asToolCalls();

        $this->assertCount(1, $toolCalls);
        $this->assertInstanceOf(ToolCall::class, $toolCalls[0]);
        $this->assertSame('toolu_mantle_01', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $toolCalls[0]->getArguments());
    }

    protected function createPlatform(HttpClientInterface $httpClient): PlatformInterface
    {
        return Factory::createPlatform(
            apiKey: 'test-api-key',
            region: 'us-east-1',
            httpClient: $httpClient,
            cacheRetention: 'none',
        );
    }

    protected function cassetteDirectory(): string
    {
        return __DIR__.'/Fixtures/cassettes';
    }
}

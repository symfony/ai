<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Tests;

use Symfony\AI\Platform\Bridge\Perplexity\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Test\Replay\AbstractBridgeReplayTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Replays recorded Perplexity Agent API interactions through the real bridge pipeline.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ReplayTest extends AbstractBridgeReplayTestCase
{
    public function testText()
    {
        $platform = $this->platformForCassette('text');

        $result = $platform->invoke('fast', new MessageBag(Message::ofUser('What is the capital of France? Answer in one sentence.')));

        $this->assertSame('Paris is the capital of France.[1]', $result->asText());
        $this->assertCount(10, $result->getMetadata()->get('search_results'));
        $this->assertSame('https://www.britannica.com/place/Paris', $result->getMetadata()->get('citations')[0]);
    }

    public function testStreamingText()
    {
        $platform = $this->platformForCassette('streaming_text');

        $result = $platform->invoke('fast', new MessageBag(Message::ofUser('What is the capital of France? Answer in one sentence.')), ['stream' => true]);

        $deltas = iterator_to_array($result->asStream(), false);

        $text = '';
        foreach ($deltas as $delta) {
            if ($delta instanceof TextDelta) {
                $text .= $delta->getText();
            }
        }

        $this->assertSame('The capital of France is Paris[1].', $text);
        $this->assertSame([], array_filter($deltas, static fn ($delta): bool => $delta instanceof ToolCallStart), 'the built-in web search is not reported as a tool call');
        $this->assertCount(10, $result->getMetadata()->get('search_results'));
        $this->assertSame('https://www.britannica.com/place/Paris', $result->getMetadata()->get('citations')[0]);
    }

    public function testToolCall()
    {
        $platform = $this->platformForCassette('tool_call');

        $result = $platform->invoke('perplexity/sonar', new MessageBag(Message::ofUser('What is the weather in Paris right now? Use the tool.')), [
            'tools' => [[
                'type' => 'function',
                'name' => 'get_weather',
                'description' => 'Get the current weather for a city',
                'parameters' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']], 'required' => ['city']],
            ]],
        ]);

        $toolCalls = $result->asToolCalls();

        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_fFbaGTeBsRXhBC4Hauv6DZxV', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $toolCalls[0]->getArguments());
    }

    protected function createPlatform(HttpClientInterface $httpClient): PlatformInterface
    {
        return Factory::createPlatform('pplx-test-api-key', $httpClient);
    }

    protected function cassetteDirectory(): string
    {
        return __DIR__.'/Fixtures/cassettes';
    }
}

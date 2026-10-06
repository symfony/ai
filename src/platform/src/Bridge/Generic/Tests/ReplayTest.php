<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\Bridge\Generic\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\Replay\AbstractBridgeReplayTestCase;
use Symfony\AI\Platform\Test\Replay\CassetteHttpClient;
use Symfony\AI\Platform\Test\Replay\HttpCassette;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Replays the response shape of https://github.com/symfony/ai/issues/2542: a tool call reported with
 * a "stop" finish reason and a null message.content.
 *
 * The tool-call cassettes are hand-seeded from a stub OpenAI-compatible gateway rather than recorded against a
 * hosted provider - the managed gateways reachable from CI normalize the finish reason back to
 * "tool_calls", so none of them reproduces it, while self-hosted ones do. They replay offline like
 * any other cassette; the localhost URL they carry is the stub they were seeded from and is never
 * dialed.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReplayTest extends AbstractBridgeReplayTestCase
{
    public function testToolCallWithStopFinishReason()
    {
        $platform = $this->platformForCassette('tool_call_with_stop_finish_reason');

        $result = $platform->invoke('gpt-oss-120b', new MessageBag(Message::ofUser('What time is it?')));

        $this->assertInstanceOf(ToolCallResult::class, $result->getResult());

        $toolCalls = $result->asToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_1', $toolCalls[0]->getId());
        $this->assertSame('clock', $toolCalls[0]->getName());

        // The raw reason is reported as the gateway sent it, not rewritten to "tool_calls".
        $this->assertSame('stop', $result->getResult()->getMetadata()->get('finish_reason')->getRaw());
    }

    public function testStreamingToolCallWithStopFinishReason()
    {
        $platform = $this->platformForCassette('streaming_tool_call_with_stop_finish_reason');

        $result = $platform->invoke('gpt-oss-120b', new MessageBag(Message::ofUser('What time is it?')), ['stream' => true]);

        $deltas = iterator_to_array($result->asStream(), false);

        $starts = array_values(array_filter($deltas, static fn ($delta): bool => $delta instanceof ToolCallStart));
        $this->assertCount(1, $starts, 'the tool call opens exactly once');

        $completed = array_values(array_filter($deltas, static fn ($delta): bool => $delta instanceof ToolCallComplete));
        $this->assertCount(1, $completed, 'a "stop" finish reason completes the accumulated tool call');

        $toolCalls = $completed[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_1', $toolCalls[0]->getId());
        $this->assertSame('clock', $toolCalls[0]->getName());

        // StreamListener promotes the finish reason off the visible stream onto the result metadata.
        $this->assertSame('stop', $result->getResult()->getMetadata()->get('finish_reason')->getRaw());
    }

    public function testStreamingUsageIsNotCountedTwice()
    {
        // Recorded from OpenCode Go: usage appears on both the terminal choice and the trailing usage-only chunk.
        $httpClient = new CassetteHttpClient(new HttpCassette($this->cassetteDirectory().'/opencode_go_duplicate_usage.json'), record: false);
        $platform = Factory::createPlatform('https://opencode.ai/zen/go', 'test-api-key', $httpClient);

        $result = $platform->invoke('deepseek-v4.1-flash', new MessageBag(Message::ofUser('Reply with the word hello.')), [
            'stream' => true,
            'max_tokens' => 32,
        ]);

        iterator_to_array($result->asStream(), false);

        $usage = $result->getResult()->getMetadata()->get('token_usage');
        $this->assertSame(36, $usage->getPromptTokens());
        $this->assertSame(32, $usage->getCompletionTokens());
        $this->assertSame(32, $usage->getThinkingTokens());
        $this->assertSame(68, $usage->getTotalTokens());
    }

    protected function createPlatform(HttpClientInterface $httpClient): PlatformInterface
    {
        return Factory::createPlatform('http://127.0.0.1:8799', 'test-api-key', $httpClient, new ModelCatalog([
            'gpt-oss-120b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
        ]));
    }

    protected function cassetteDirectory(): string
    {
        return __DIR__.'/Fixtures/cassettes';
    }
}

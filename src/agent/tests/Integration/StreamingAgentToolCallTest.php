<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class StreamingAgentToolCallTest extends TestCase
{
    public function testTokenUsageAggregationWithStreamingAndMultipleToolCalls()
    {
        [$result, $invocationCount] = $this->executeAgentScenario();

        $metadata = $result->getMetadata();

        $this->assertSame(4, $invocationCount);
        $this->assertTrue($metadata->has('token_usage'));
        $this->assertInstanceOf(TokenUsageAggregation::class, $tokenUsage = $metadata->get('token_usage'));
        $this->assertSame(4, $tokenUsage->count());
        $this->assertSame(175 + 280 + 385 + 560, $tokenUsage->getTotalTokens());
    }

    public function testSourcesCollectedFromAllToolCallsInStreaming()
    {
        [$result, $invocationCount] = $this->executeAgentScenario();

        $metadata = $result->getMetadata();

        $this->assertSame(4, $invocationCount);
        $this->assertTrue($metadata->has('sources'));
        $this->assertInstanceOf(SourceCollection::class, $sources = $metadata->get('sources'));
        $this->assertCount(2, $sources);
    }

    /**
     * Executes the "Who's the mayor of Berlin?" agent scenario.
     *
     * The scenario involves:
     * 1. Agent calls clock tool to get current date (produces 1 source)
     * 2. Agent calls wikipedia_search tool (no source)
     * 3. Agent calls wikipedia_article tool (produces 1 source)
     * 4. Agent provides final answer
     *
     * @return array{0: ResultInterface, 1: int}
     */
    private function executeAgentScenario(): array
    {
        $clock = new Clock(new MockClock(new \DateTimeImmutable('2025-03-15 14:30:00')));
        $wikipedia = new Wikipedia(new MockHttpClient([
            // Wikipedia search response
            new JsonMockResponse([
                'query' => [
                    'search' => [
                        ['title' => 'Kai Wegner'],
                        ['title' => 'Governing Mayor of Berlin'],
                    ],
                ],
            ]),
            // Wikipedia article response
            new JsonMockResponse([
                'query' => [
                    'pages' => [
                        '12345' => [
                            'title' => 'Kai Wegner',
                            'extract' => <<<CONTENT
                                Kai Wegner (born 18 October 1972) is a German politician of the CDU who has been serving
                                as the Governing Mayor of Berlin since 27 April 2023.'
                                CONTENT,
                        ],
                    ],
                ],
            ]),
        ]));
        $toolbox = new Toolbox([$clock, $wikipedia]);

        $invocationCount = 0;
        $platform = new InMemoryPlatform(function ($model, $input, $options) use (&$invocationCount) {
            ++$invocationCount;

            return match ($invocationCount) {
                1 => $this->createStreamResultWithToolCall(
                    'Let me check the current date first.',
                    new ToolCall('call_clock_1', 'clock', []),
                    new TokenUsage(promptTokens: 150, completionTokens: 25, totalTokens: 175)
                ),
                2 => $this->createStreamResultWithToolCall(
                    'Now I\'ll search Wikipedia for the mayor of Berlin.',
                    new ToolCall('call_wiki_search_1', 'wikipedia_search', ['query' => 'Berlin mayor 2025']),
                    new TokenUsage(promptTokens: 250, completionTokens: 30, totalTokens: 280)
                ),
                3 => $this->createStreamResultWithToolCall(
                    'Let me read about Kai Wegner.',
                    new ToolCall('call_wiki_article_1', 'wikipedia_article', ['title' => 'Kai Wegner']),
                    new TokenUsage(promptTokens: 350, completionTokens: 35, totalTokens: 385)
                ),
                4 => $this->createTextResultWithTokenUsage(
                    'The Governing Mayor of Berlin is Kai Wegner (CDU), serving since April 2023.',
                    new TokenUsage(promptTokens: 500, completionTokens: 60, totalTokens: 560)
                ),
                default => new TextResult('Unexpected call'),
            };
        });

        $processor = new AgentProcessor($toolbox, includeSources: true);
        $agent = new Agent($platform, 'gpt-4', [$processor], [$processor]);

        $messages = new MessageBag(
            Message::forSystem('You are a helpful assistant. Check the current date for time-sensitive questions.'),
            Message::ofUser('Who is the mayor of Berlin?'),
        );

        $result = $agent->call($messages, ['stream' => true]);

        // Consume the stream to trigger all tool calls
        $content = '';
        foreach ($result->getContent() as $chunk) {
            if (\is_string($chunk)) {
                $content .= $chunk;
            }
        }

        // Basic sanity check that the scenario executed correctly
        $this->assertStringContainsString('Kai Wegner', $content);

        return [$result, $invocationCount];
    }

    private function createStreamResultWithToolCall(string $text, ToolCall $toolCall, TokenUsage $tokenUsage): StreamResult
    {
        $result = new StreamResult((function () use ($text, $toolCall) {
            yield $text;
            yield new ToolCallResult($toolCall);
        })());
        $result->getMetadata()->add('token_usage', $tokenUsage);

        return $result;
    }

    private function createTextResultWithTokenUsage(string $text, TokenUsage $tokenUsage): TextResult
    {
        $result = new TextResult($text);
        $result->getMetadata()->add('token_usage', $tokenUsage);

        return $result;
    }
}

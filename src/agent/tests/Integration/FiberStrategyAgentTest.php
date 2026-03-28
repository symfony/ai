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
use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\ExecutionStrategy\FiberToolExecutionStrategy;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class FiberStrategyAgentTest extends TestCase
{
    public function testAgentCompletesWithFiberStrategyAndSingleToolCall()
    {
        $wikipedia = new Wikipedia(new MockHttpClient([
            new JsonMockResponse([
                'query' => [
                    'search' => [
                        ['title' => 'Symfony'],
                    ],
                ],
            ]),
        ]));

        $toolbox = new Toolbox([$wikipedia]);

        $invocationCount = 0;
        $platform = new InMemoryPlatform(static function ($model, $input, $options) use (&$invocationCount) {
            ++$invocationCount;

            return match ($invocationCount) {
                1 => new ToolCallResult(new ToolCall('call_wiki_1', 'wikipedia_search', ['query' => 'Symfony PHP'])),
                2 => new TextResult('Symfony is a PHP web framework.'),
                default => new TextResult('Unexpected call'),
            };
        });

        $strategy = new FiberToolExecutionStrategy();
        $processor = new AgentProcessor($toolbox, executionStrategy: $strategy);
        $agent = new Agent($platform, 'gpt-4', [$processor], [$processor]);

        $messages = new MessageBag(
            Message::ofUser('What is Symfony?'),
        );

        $result = $agent->call($messages);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Symfony is a PHP web framework.', $result->getContent());
        $this->assertSame(2, $invocationCount);
    }

    public function testAgentCollectsSourcesAcrossParallelToolCallsWithFiberStrategy()
    {
        $wikipedia = new Wikipedia(new MockHttpClient([
            new JsonMockResponse([
                'query' => [
                    'pages' => [
                        '1' => [
                            'title' => 'Symfony',
                            'extract' => 'Symfony is a PHP framework.',
                        ],
                    ],
                ],
            ]),
            new JsonMockResponse([
                'query' => [
                    'pages' => [
                        '2' => [
                            'title' => 'Laravel',
                            'extract' => 'Laravel is another PHP framework.',
                        ],
                    ],
                ],
            ]),
        ]));

        $toolbox = new Toolbox([$wikipedia]);

        $toolCallSymfony = new ToolCall('call_symfony', 'wikipedia_article', ['title' => 'Symfony']);
        $toolCallLaravel = new ToolCall('call_laravel', 'wikipedia_article', ['title' => 'Laravel']);

        $invocationCount = 0;
        $platform = new InMemoryPlatform(static function ($model, $input, $options) use (&$invocationCount, $toolCallSymfony, $toolCallLaravel) {
            ++$invocationCount;

            return match ($invocationCount) {
                1 => new ToolCallResult($toolCallSymfony, $toolCallLaravel),
                2 => new TextResult('Symfony and Laravel are both PHP frameworks.'),
                default => new TextResult('Unexpected call'),
            };
        });

        $strategy = new FiberToolExecutionStrategy();
        $processor = new AgentProcessor($toolbox, executionStrategy: $strategy, includeSources: true);
        $agent = new Agent($platform, 'gpt-4', [$processor], [$processor]);

        $messages = new MessageBag(
            Message::ofUser('Compare Symfony and Laravel.'),
        );

        $result = $agent->call($messages);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame(2, $invocationCount);

        $metadata = $result->getMetadata();
        $this->assertTrue($metadata->has('sources'));
        $this->assertInstanceOf(SourceCollection::class, $sources = $metadata->get('sources'));
        $this->assertCount(2, $sources);
    }

    public function testAgentAggregatesTokenUsageWithFiberStrategy()
    {
        $wikipedia = new Wikipedia(new MockHttpClient([
            new JsonMockResponse([
                'query' => [
                    'search' => [['title' => 'PHP']],
                ],
            ]),
        ]));

        $toolbox = new Toolbox([$wikipedia]);

        $invocationCount = 0;
        $platform = new InMemoryPlatform(static function ($model, $input, $options) use (&$invocationCount) {
            ++$invocationCount;

            if (1 === $invocationCount) {
                $result = new ToolCallResult(new ToolCall('call_php', 'wikipedia_search', ['query' => 'PHP programming']));
                $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 100, completionTokens: 20, totalTokens: 120));

                return $result;
            }

            $result = new TextResult('PHP is a server-side scripting language.');
            $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 150, completionTokens: 30, totalTokens: 180));

            return $result;
        });

        $strategy = new FiberToolExecutionStrategy();
        $processor = new AgentProcessor($toolbox, executionStrategy: $strategy);
        $agent = new Agent($platform, 'gpt-4', [$processor], [$processor]);

        $messages = new MessageBag(
            Message::ofUser('Tell me about PHP.'),
        );

        $result = $agent->call($messages);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame(2, $invocationCount);
        // In non-streaming mode the final result's token_usage is a plain TokenUsage.
        $tokenUsage = $result->getMetadata()->get('token_usage');
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(180, $tokenUsage->getTotalTokens());
    }
}

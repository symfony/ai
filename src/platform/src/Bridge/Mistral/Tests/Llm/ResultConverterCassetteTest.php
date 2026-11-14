<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Llm;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Test\Replay\CassetteHttpClient;
use Symfony\AI\Platform\Test\Replay\HttpCassette;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * Drives the real Mistral bridge pipeline (Contract, ModelClient, Llm\ResultConverter) against
 * cassettes recorded from api.mistral.ai, proving record/replay exercises bridge internals offline.
 *
 * Assertions on the shape of a provider response belong here rather than in
 * {@see ResultConverterTest}, which keeps the checks that do not depend on a payload.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultConverterCassetteTest extends TestCase
{
    public function testReplaysTextResponseThroughRealConverter()
    {
        $result = $this->replay('text_response.json', 'Hello');

        $this->assertSame(
            "Hello! 😊 How can I assist you today? Whether you have a question, need help with something, or just want to chat, I'm here for you!",
            $result->asText(),
        );
        $this->assertTrue($result->getResult()->getMetadata()->get('finish_reason')->is(FinishReasonCase::STOP));
    }

    public function testReplaysTokenUsageThroughRealConverter()
    {
        $result = $this->replay('text_response.json', 'Hello');

        $tokenUsage = $result->getResult()->getMetadata()->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(4, $tokenUsage->getPromptTokens());
        $this->assertSame(36, $tokenUsage->getCompletionTokens());
        $this->assertSame(40, $tokenUsage->getTotalTokens());
        // x-ratelimit-limit-tokens-minute is 400000, the request cost 40 tokens
        $this->assertSame(399960, $tokenUsage->getRemainingTokensMinute());
    }

    public function testReplaysTruncatedTextResponseThroughRealConverter()
    {
        $result = $this->replay('truncated_response.json', 'Write a long essay about the Eiffel Tower.', ['max_tokens' => 5]);

        $this->assertSame('# **The Eiff', $result->asText());
        $this->assertTrue($result->getResult()->getMetadata()->get('finish_reason')->is(FinishReasonCase::LENGTH));
    }

    public function testReplaysToolCallResponseThroughRealConverter()
    {
        $result = $this->replay('tool_call_response.json', 'What is the weather in Paris?', [
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => 'Get the current weather for a location',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['location' => ['type' => 'string', 'description' => 'The city, e.g. Paris']],
                        'required' => ['location'],
                    ],
                ],
            ]],
            'tool_choice' => 'any',
        ]);

        $toolCalls = $result->asToolCalls();

        $this->assertCount(1, $toolCalls);
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Paris'], $toolCalls[0]->getArguments());
    }

    public function testReplaysBadRequestThroughRealConverter()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid model: mistral-does-not-exist');

        $this->replay('bad_request_response.json', 'Hello', model: new Mistral('mistral-does-not-exist'))->asText();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function replay(string $cassette, string $prompt, array $options = [], string|Mistral $model = 'mistral-large-latest'): DeferredResult
    {
        $platform = Factory::createPlatform('test-key', new CassetteHttpClient(
            new HttpCassette(__DIR__.'/../Fixtures/cassettes/'.$cassette),
            null,
            false,
        ));

        return $platform->invoke($model, new MessageBag(Message::ofUser($prompt)), $options);
    }
}

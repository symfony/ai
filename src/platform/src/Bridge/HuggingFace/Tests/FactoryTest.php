<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\Replay\CassetteHttpClient;
use Symfony\AI\Platform\Test\Replay\HttpCassette;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FactoryTest extends TestCase
{
    #[TestDox('Creates Platform with default provider and auto-generated components')]
    public function testCreateWithDefaults()
    {
        $platform = Factory::createPlatform('test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestDox('Creates Platform with custom provider')]
    public function testCreateWithCustomProvider()
    {
        $platform = Factory::createPlatform('test-api-key', Provider::COHERE);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestDox('Handles EventSourceHttpClient correctly')]
    public function testCreateWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('test-api-key', Provider::HF_INFERENCE, $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestDox('Creates Platform successfully with all supported providers')]
    #[TestWith([Provider::CEREBRAS])]
    #[TestWith([Provider::COHERE])]
    #[TestWith([Provider::FAL_AI])]
    #[TestWith([Provider::FEATHERLESS_AI])]
    #[TestWith([Provider::FIREWORKS])]
    #[TestWith([Provider::GROQ])]
    #[TestWith([Provider::HF_INFERENCE])]
    #[TestWith([Provider::HYPERBOLIC])]
    #[TestWith([Provider::NEBIUS])]
    #[TestWith([Provider::NOVITA])]
    #[TestWith([Provider::NSCALE])]
    #[TestWith([Provider::PUBLIC_AI])]
    #[TestWith([Provider::REPLICATE])]
    #[TestWith([Provider::SAMBA_NOVA])]
    #[TestWith([Provider::SCALEWAY])]
    #[TestWith([Provider::TOGETHER])]
    #[TestWith([Provider::WAVE_SPEED_AI])]
    #[TestWith([Provider::Z_AI])]
    public function testCreateWithDifferentProviders(string $provider)
    {
        $platform = Factory::createPlatform('test-api-key', $provider);
        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestWith([Provider::HF_INFERENCE, 'https://router.huggingface.co/hf-inference/models/test/model/v1/chat/completions'])]
    #[TestWith([Provider::TOGETHER, 'https://router.huggingface.co/together/v1/chat/completions'])]
    public function testToolCallingRoundTrip(string $provider, string $expectedUrl)
    {
        $toolCall = ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'weather', 'arguments' => '{"city":"Paris"}']];
        $responses = [
            new JsonMockResponse(['choices' => [['message' => ['content' => null, 'tool_calls' => [$toolCall]], 'finish_reason' => 'tool_calls']]]),
            new JsonMockResponse(['choices' => [['message' => ['content' => 'It is sunny in Paris.'], 'finish_reason' => 'stop']]]),
        ];
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $responses): JsonMockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR)];

            return $responses[\count($requests) - 1];
        });
        $platform = Factory::createPlatform('test-api-key', $provider, $httpClient);
        $this->assertTrue($platform->getModelCatalog()->getModel('test/model')->supports(Capability::TOOL_CALLING));

        $messages = new MessageBag(Message::ofUser('What is the weather in Paris?'));
        $options = [
            'task' => Task::CHAT_COMPLETION,
            'tools' => [new Tool(new ExecutionReference(self::class), 'weather', 'Get the weather', [
                'type' => 'object',
                'properties' => ['city' => ['type' => 'string', 'description' => 'The city to look up']],
                'required' => ['city'],
                'additionalProperties' => false,
            ])],
            'tool_choice' => 'auto',
        ];
        $result = $platform->invoke('test/model', $messages, $options)->getResult();

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertSame(['city' => 'Paris'], $result->getContent()[0]->getArguments());

        $messages->add(Message::ofAssistant($result));
        $messages->add(Message::ofToolCall($result->getContent()[0], 'sunny'));

        $this->assertSame('It is sunny in Paris.', $platform->invoke('test/model', $messages, $options)->asText());
        $this->assertCount(2, $requests);
        foreach ($requests as $request) {
            $this->assertSame('POST', $request['method']);
            $this->assertSame($expectedUrl, $request['url']);
            $this->assertSame('auto', $request['body']['tool_choice']);
            $this->assertSame('weather', $request['body']['tools'][0]['function']['name']);
            $this->assertSame('function', $request['body']['tools'][0]['type']);
            $this->assertSame(['city' => ['type' => 'string', 'description' => 'The city to look up']], $request['body']['tools'][0]['function']['parameters']['properties']);
            $this->assertArrayNotHasKey('task', $request['body']);
            if (Provider::HF_INFERENCE !== $provider) {
                $this->assertSame('test/model', $request['body']['model']);
            }
        }
        $this->assertSame([
            ['role' => 'user', 'content' => 'What is the weather in Paris?'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => [$toolCall]],
            ['role' => 'tool', 'content' => 'sunny', 'tool_call_id' => 'call_1'],
        ], $requests[1]['body']['messages']);
    }

    public function testReplayToolCall()
    {
        $httpClient = new CassetteHttpClient(new HttpCassette(__DIR__.'/Fixtures/cassettes/tool_call.json'), record: false);
        $platform = Factory::createPlatform('test-api-key', Provider::CEREBRAS, $httpClient);

        $calls = $platform->invoke('openai/gpt-oss-120b', new MessageBag(Message::ofUser('What is the current date and time?')), [
            'task' => Task::CHAT_COMPLETION,
        ])->asToolCalls();

        $this->assertCount(1, $calls);
        $this->assertSame('call_clock', $calls[0]->getId());
        $this->assertSame('clock', $calls[0]->getName());
        $this->assertSame([], $calls[0]->getArguments());
    }
}

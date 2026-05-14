<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\ChatCompletionsClient;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Bridge\OpenAi\ResponsesClient;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * The headline test for the new dispatch design: a single GPT model invoked
 * over both `openai.responses` (default) and `openai.chat_completions`
 * (per-call override) from the same {@see Platform} — the multi-contract
 * scenario the previous design (one Model subclass = one ModelClient = one
 * URL) could not satisfy.
 *
 * Also verifies that the catalog's per-class endpoint mapping (Embeddings →
 * /v1/embeddings, etc.) routes through the same dispatcher correctly.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ContractDispatcherIntegrationTest extends TestCase
{
    public function testGptDefaultsToResponsesEndpoint()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'output' => [
                    [
                        'type' => 'message',
                        'role' => 'assistant',
                        'id' => 'msg_1',
                        'content' => [['type' => 'output_text', 'text' => 'responses says hi']],
                    ],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 4],
            ]);
        });

        $platform = Factory::createPlatform('sk-test', $httpClient);
        $result = $platform->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')));

        $this->assertSame('https://api.openai.com/v1/responses', $capturedUrl);
        $this->assertSame('gpt-4o', $capturedBody['model']);
        $this->assertArrayHasKey('input', $capturedBody, 'Responses API expects "input", not "messages"');

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('responses says hi', $textResult->getContent());
    }

    public function testGptCanBeInvokedOverChatCompletionsEndpoint()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'chat says hi'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 4],
            ]);
        });

        $platform = Factory::createPlatform('sk-test', $httpClient);
        $result = $platform->invoke(
            'gpt-4o',
            new MessageBag(Message::ofUser('hi')),
            ['endpoint' => ChatCompletionsClient::ENDPOINT],
        );

        $this->assertSame('https://api.openai.com/v1/chat/completions', $capturedUrl);
        $this->assertSame('gpt-4o', $capturedBody['model']);
        $this->assertArrayHasKey('messages', $capturedBody, 'Chat Completions API expects "messages", not "input"');
        $this->assertArrayNotHasKey('input', $capturedBody);

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('chat says hi', $textResult->getContent());
    }

    public function testSamePlatformInstanceServesBothContractsForSameModel()
    {
        $callCount = 0;
        $capturedUrls = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$callCount, &$capturedUrls) {
            ++$callCount;
            $capturedUrls[] = $url;

            if (str_contains($url, '/v1/responses')) {
                return new JsonMockResponse([
                    'output' => [['type' => 'message', 'role' => 'assistant', 'id' => 'm', 'content' => [['type' => 'output_text', 'text' => 'A']]]],
                ]);
            }

            return new JsonMockResponse([
                'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'B'], 'finish_reason' => 'stop']],
            ]);
        });

        $platform = Factory::createPlatform('sk-test', $httpClient);

        $a = $platform->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')));
        $b = $platform->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')), ['endpoint' => ChatCompletionsClient::ENDPOINT]);
        $c = $platform->invoke('gpt-4o', new MessageBag(Message::ofUser('hi')), ['endpoint' => ResponsesClient::ENDPOINT]);

        $this->assertSame(3, $callCount);
        $this->assertSame('https://api.openai.com/v1/responses', $capturedUrls[0]);
        $this->assertSame('https://api.openai.com/v1/chat/completions', $capturedUrls[1]);
        $this->assertSame('https://api.openai.com/v1/responses', $capturedUrls[2]);

        $this->assertSame('A', $a->getResult()->getContent());
        $this->assertSame('B', $b->getResult()->getContent());
        $this->assertSame('A', $c->getResult()->getContent());
    }

    public function testEmbeddingsEndpointRoutesThroughDispatcher()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                    ['embedding' => [0.4, 0.5, 0.6]],
                ],
                'usage' => ['prompt_tokens' => 2, 'total_tokens' => 2],
            ]);
        });

        $platform = Factory::createPlatform('sk-test', $httpClient);
        $result = $platform->invoke('text-embedding-3-small', ['hello', 'world']);

        $this->assertSame('https://api.openai.com/v1/embeddings', $capturedUrl);
        $this->assertSame('text-embedding-3-small', $capturedBody['model']);
        $this->assertSame(['hello', 'world'], $capturedBody['input']);

        $vectorResult = $result->getResult();
        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertCount(2, $vectorResult->getContent());
    }
}

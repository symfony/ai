<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Decision\Answer\Answers;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class PlatformTest extends TestCase
{
    public function testItAnswersTheQuestionsThroughTheDecisionsApi()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.openai.com/v1/decisions', $url);
            $this->assertSame('{"model":"gpt-6-luna","input":"I need this fixed by tomorrow.","questions":[{"name":"is_urgent","type":"predicate","instructions":"Does this convey urgency?"}]}', $options['body']);

            return new JsonMockResponse(['model' => 'gpt-6-luna', 'answers' => [['type' => 'predicate', 'name' => 'is_urgent', 'probability' => 0.97]], 'usage' => ['input_tokens' => 40, 'output_tokens' => 0, 'total_tokens' => 40]]);
        });

        $result = Factory::createPlatform('sk-api-key', $httpClient)->invoke('gpt-6-luna', 'I need this fixed by tomorrow.', ['questions' => [
            'is_urgent' => new NoulQuestion('Does this convey urgency?'),
        ]]);

        $this->assertTrue($result->asObject(Answers::class)->getNoul('is_urgent')->isTrue());
        $this->assertSame(40, $result->getMetadata()->get('token_usage')->getPromptTokens());
    }

    public function testItKeepsTheModelOnTheResponsesApiWithoutQuestions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('https://api.openai.com/v1/responses', $url);

            return new JsonMockResponse(['id' => 'resp_1', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'pong']]]]]);
        });

        $result = Factory::createPlatform('sk-api-key', $httpClient)->invoke('gpt-6-luna', new MessageBag(Message::ofUser('ping')));

        $this->assertSame('pong', $result->asText());
    }

    public function testItRejectsAMessageBagBeforeSendingARequest()
    {
        $httpClient = new MockHttpClient();

        try {
            Factory::createPlatform('sk-api-key', $httpClient)->invoke('gpt-6-luna', new MessageBag(Message::forSystem('You triage tickets of Acme.'), Message::ofUser('hi')), ['questions' => [
                'is_urgent' => new NoulQuestion('Does this convey urgency?'),
            ]]);
            $this->fail('A message bag must not reach the Decisions API.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The decision model "gpt-6-luna" requires a string, an array, a "Text", an "Image" or a "Collection" input.', $e->getMessage());
        }

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testItRejectsBatchedQuestionsBeforeSendingARequest()
    {
        $httpClient = new MockHttpClient();

        try {
            Factory::createPlatform('sk-api-key', $httpClient)->invoke('gpt-6-luna', 'I need this fixed by tomorrow.', [
                'questions' => ['is_urgent' => new NoulQuestion('Does this convey urgency?')],
                'batch' => true,
            ]);
            $this->fail('Decisions must not be batched.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The questions of a decision cannot be batched.', $e->getMessage());
        }

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testItRejectsQuestionsForModelsWithoutDecisions()
    {
        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage('does not support "decision".');

        Factory::createPlatform('sk-api-key', new MockHttpClient())->invoke('gpt-5.6-luna', 'state', ['questions' => [
            'is_urgent' => new NoulQuestion('Does this convey urgency?'),
        ]]);
    }
}

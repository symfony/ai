<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\Decision\ModelClient;
use Symfony\AI\Platform\Bridge\OpenRouter\DecisionModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new DecisionModel('typesafe/jev-1.13')));
        $this->assertFalse($client->supports(new CompletionsModel('openrouter/auto')));
    }

    public function testItSendsTheEvaluationToTheDecisionsEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://openrouter.ai/api/alpha/decisions', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertSame('{"model":"typesafe\/jev-1.13","state":"My invoice is wrong.","questions":{"department":{"type":"choice","instructions":"Which team should handle this?","criteria":{"billing":"Payment issues","technical":null}},"frustration":{"type":"score","instructions":"How frustrated is the customer?","criteria":["Calm","Angry"]},"is_bug":{"type":"noul","instructions":"Is this a software defect?","criteria":{"true":"Broken behavior","false":"A question"}}}}', $options['body']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new DecisionModel('typesafe/jev-1.13'), (new Evaluation('My invoice is wrong.', [
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payment issues', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Angry']),
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question'),
        ]))->jsonSerialize());
    }

    public function testItForwardsTheSupportedOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);

            $this->assertSame(['order' => ['typesafe'], 'allow_fallbacks' => false], $body['provider']);
            $this->assertSame('session-1', $body['session_id']);
            $this->assertSame(['trace_id' => 'trace-1'], $body['trace']);
            $this->assertSame('user-1', $body['user']);
            $this->assertArrayNotHasKey('temperature', $body);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->createPayload(), [
            'provider' => ['order' => ['typesafe'], 'allow_fallbacks' => false],
            'session_id' => 'session-1',
            'trace' => ['trace_id' => 'trace-1'],
            'user' => 'user-1',
            'temperature' => 0.5,
        ]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://openrouter.example.com/alpha/decisions', $url);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key', 'https://openrouter.example.com/');
        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->createPayload());
    }

    public function testItRejectsNoulQuestionsWithoutBothCriteria()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The noul question "is_bug" requires both a "true" and a "false" criterion for OpenRouter decision models.');

        $client->request(new DecisionModel('typesafe/jev-1.13'), (new Evaluation('My invoice is wrong.', [
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior'),
        ]))->jsonSerialize());
    }

    public function testItRejectsImages()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "typesafe/jev-1.13" does not support image inputs.');

        $client->request(new DecisionModel('typesafe/jev-1.13'), (new Evaluation('Is this a cat?', [
            'is_cat' => new NoulQuestion('Does the image show a cat?', 'A cat', 'No cat'),
        ], [new Image('image data', 'image/png')]))->jsonSerialize());
    }

    public function testItRejectsInvalidPayloads()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        $client->request(new DecisionModel('typesafe/jev-1.13'), 'invalid string payload');
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(): array
    {
        return (new Evaluation('My invoice is wrong.', [
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question'),
        ]))->jsonSerialize();
    }
}

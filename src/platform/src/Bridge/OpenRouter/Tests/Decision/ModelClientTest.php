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
use Symfony\AI\Platform\Bridge\Decision\Contract\InputNormalizer;
use Symfony\AI\Platform\Bridge\Decision\Contract\QuestionNormalizer;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\Decision\ModelClient;
use Symfony\AI\Platform\Bridge\OpenRouter\DecisionModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
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

    public function testItSendsTheDecisionToTheDecisionsEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://openrouter.ai/api/alpha/decisions', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertSame('{"model":"typesafe\/jev-1.13","state":"My invoice is wrong.","questions":{"department":{"type":"choice","instructions":"Which team should handle this?","criteria":{"billing":"Payment issues","technical":null}},"frustration":{"type":"score","instructions":"How frustrated is the customer?","criteria":["Calm","Angry"]},"is_bug":{"type":"noul","instructions":"Is this a software defect?","criteria":{"true":"Broken behavior","false":"A question"}}}}', $options['body']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->normalizeInput('My invoice is wrong.'), $this->createOptions([
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payment issues', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Angry']),
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question'),
        ]));
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
        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->normalizeInput('My invoice is wrong.'), $this->createOptions() + [
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
        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->normalizeInput('My invoice is wrong.'), $this->createOptions());
    }

    public function testItAcceptsNoulQuestionsWithoutCriteria()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('{"model":"typesafe\/jev-1.13","state":"My invoice is wrong.","questions":{"is_bug":{"type":"noul","instructions":"Is this a software defect?"}}}', $options['body']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->normalizeInput('My invoice is wrong.'), $this->createOptions([
            'is_bug' => new NoulQuestion('Is this a software defect?'),
        ]));

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRejectsNoulQuestionsWithPartialCriteria()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The noul question "is_bug" requires both a "true" and a "false" criterion, or none, for OpenRouter decision models.');

        $client->request(new DecisionModel('typesafe/jev-1.13'), $this->normalizeInput('My invoice is wrong.'), $this->createOptions([
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior'),
        ]));
    }

    public function testItRejectsImages()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "typesafe/jev-1.13" does not support image inputs.');

        $this->normalizeInput(new Collection(new Text('Is this a cat?'), new Image('image data', 'image/png')));
    }

    public function testItRejectsInvocationsWithoutQuestions()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "typesafe/jev-1.13" requires the "questions" option.');

        $client->request(new DecisionModel('typesafe/jev-1.13'), 'invalid string payload');
    }

    /**
     * @param array<string, QuestionInterface>|null $questions
     *
     * @return array{questions: array<string, mixed>}
     */
    private function createOptions(?array $questions = null): array
    {
        $questions ??= ['is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question')];

        $normalizer = new QuestionNormalizer();

        return ['questions' => array_map(static fn (QuestionInterface $question): array => $normalizer->normalize($question), $questions)];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInput(string|Collection $input): array
    {
        return (new InputNormalizer())->normalize($input, context: [Contract::CONTEXT_MODEL => new DecisionModel('typesafe/jev-1.13', [Capability::DECISION])]);
    }
}

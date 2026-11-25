<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\Decision\ModelClient;
use Symfony\AI\Platform\Bridge\Ovh\DecisionModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new DecisionModel('clef-flash')));
        $this->assertFalse($client->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItSendsTheEvaluationToTheSystemOneEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/api/v1/systemone', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertSame('{"model":"clef-flash","state":"My invoice is wrong.","questions":{"department":{"type":"choice","instructions":"Which team should handle this?","criteria":{"billing":"Payment issues","technical":null}},"frustration":{"type":"score","instructions":"How frustrated is the customer?","criteria":["Calm","Angry"]},"is_bug":{"type":"noul","instructions":"Is this a software defect?","criteria":{"true":"Broken behavior","false":"A question"}}}}', $options['body']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new DecisionModel('clef-flash'), (new Evaluation('My invoice is wrong.', [
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payment issues', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Angry']),
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question'),
        ]))->jsonSerialize());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItHonorsCustomBaseUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://example.com/api/v1/systemone', $url);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key', 'https://example.com/'))->request(new DecisionModel('clef-flash'), $this->createPayload());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRejectsImages()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "clef-flash" does not support image inputs.');

        $client->request(new DecisionModel('clef-flash'), (new Evaluation('Is this a cat?', [
            'is_cat' => new NoulQuestion('Does the image show a cat?', 'A cat', 'No cat'),
        ], [new Image('image data', 'image/png')]))->jsonSerialize());
    }

    public function testItRejectsInvalidPayloads()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision payload must be an array with a "state" key and a "questions" map.');

        $client->request(new DecisionModel('clef-flash'), 'not an evaluation');
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

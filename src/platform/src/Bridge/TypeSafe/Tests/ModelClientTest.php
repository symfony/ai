<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\Jev;
use Symfony\AI\Platform\Bridge\TypeSafe\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsJevModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Jev('jev-latest')));
    }

    public function testItDoesNotSupportOtherModels()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.typesafe.ai/v1/systemone', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertContains('Content-Type: application/json', $options['headers']);

            $this->assertJsonStringEqualsJsonString(<<<'JSON'
                {
                    "model": "jev-latest",
                    "state": "My payouts have been failing for 3 days.",
                    "questions": {
                        "is_urgent": {"type": "noul", "instructions": "Does this convey urgency?"},
                        "frustration": {"type": "score", "instructions": "How frustrated is the customer?", "criteria": ["Calm", "Very angry"]}
                    }
                }
                JSON, $options['body']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Jev('jev-latest'), [
            'state' => 'My payouts have been failing for 3 days.',
            'questions' => [
                'is_urgent' => ['type' => 'noul', 'instructions' => 'Does this convey urgency?'],
                'frustration' => ['type' => 'score', 'instructions' => 'How frustrated is the customer?', 'criteria' => ['Calm', 'Very angry']],
            ],
        ]);
    }

    public function testItKeepsNumericKeysAsJsonObjects()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertJsonStringEqualsJsonString(<<<'JSON'
                {
                    "model": "jev-latest",
                    "state": ["first line", "second line"],
                    "questions": {
                        "0": {"type": "choice", "instructions": "Which line answers the query?", "criteria": {"0": "The first line", "1": null}}
                    }
                }
                JSON, $options['body']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Jev('jev-latest'), [
            'state' => ['first line', 'second line'],
            'questions' => [
                ['type' => 'choice', 'instructions' => 'Which line answers the query?', 'criteria' => ['The first line', null]],
            ],
        ]);
    }

    public function testItUsesCustomBaseUrl()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url): MockResponse {
            $this->assertSame('https://typesafe.example.com/v1/systemone', $url);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key', 'https://typesafe.example.com/');

        $client->request(new Jev('jev-latest'), [
            'state' => 'state',
            'questions' => ['is_urgent' => ['type' => 'noul', 'instructions' => 'Does this convey urgency?']],
        ]);
    }

    public function testItThrowsExceptionForStringPayload()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jev payload must be an array with a "state" key and a "questions" map.');

        $client->request(new Jev('jev-latest'), 'invalid string payload');
    }

    public function testItThrowsExceptionForMissingStateKey()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jev payload must be an array with a "state" key and a "questions" map.');

        $client->request(new Jev('jev-latest'), ['questions' => ['is_urgent' => ['type' => 'noul', 'instructions' => 'Urgent?']]]);
    }

    public function testItThrowsExceptionForMissingQuestionsKey()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jev payload must be an array with a "state" key and a "questions" map.');

        $client->request(new Jev('jev-latest'), ['state' => 'state']);
    }

    public function testItThrowsExceptionForInvalidQuestion()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question "is_urgent" must be an array, "string" given.');

        $client->request(new Jev('jev-latest'), ['state' => 'state', 'questions' => ['is_urgent' => 'Urgent?']]);
    }
}

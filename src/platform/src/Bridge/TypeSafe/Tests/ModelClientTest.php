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

        $client->request(new Jev('jev-latest'), ['state' => 'My payouts have been failing for 3 days.'], ['questions' => [
            'is_urgent' => ['type' => 'noul', 'instructions' => 'Does this convey urgency?'],
            'frustration' => ['type' => 'score', 'instructions' => 'How frustrated is the customer?', 'criteria' => ['Calm', 'Very angry']],
        ]]);
    }

    public function testItUsesCustomBaseUrl()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url): MockResponse {
            $this->assertSame('https://typesafe.example.com/v1/systemone', $url);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key', 'https://typesafe.example.com/');

        $client->request(new Jev('jev-latest'), ['state' => 'state'], ['questions' => [
            'is_urgent' => ['type' => 'noul', 'instructions' => 'Does this convey urgency?'],
        ]]);
    }

    public function testItThrowsExceptionForMissingQuestionsOption()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "jev-latest" requires the "questions" option.');

        $client->request(new Jev('jev-latest'), ['state' => 'state']);
    }

    public function testItThrowsExceptionForUnnormalizedPayload()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "jev-latest" requires a string, an array, a "Text" or a "Collection" input.');

        $client->request(new Jev('jev-latest'), 'invalid string payload', ['questions' => []]);
    }
}

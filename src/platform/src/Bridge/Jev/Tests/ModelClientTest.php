<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Jev\Jev;
use Symfony\AI\Platform\Bridge\Jev\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testItSendsExpectedRequest()
    {
        $questions = [
            'is_urgent' => [
                'type' => 'noul',
                'instructions' => 'Does this convey urgency?',
            ],
        ];
        $state = 'Help! My payouts have been failing for 3 days.';

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($state, $questions): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.typesafe.ai/v1/systemone', $url);
            $this->assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame(json_encode([
                'model' => 'jev-latest',
                'state' => $state,
                'questions' => $questions,
            ]), $options['body']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Jev('jev-latest'), $state, ['questions' => $questions]);
    }

    public function testItSendsStructuredState()
    {
        $state = ['amount' => 80, 'country' => 'NG'];
        $questions = [
            'action' => [
                'type' => 'choice',
                'instructions' => 'Should the payment be allowed?',
                'criteria' => [
                    'allow' => 'Looks normal.',
                    'block' => 'Looks abusive.',
                ],
            ],
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($state, $questions): MockResponse {
            $this->assertSame('https://api.typesafe.ai/v1/systemone', $url);
            $payload = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);
            $this->assertSame($state, $payload['state']);
            $this->assertSame($questions, $payload['questions']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Jev('jev-1.13.0'), $state, ['questions' => $questions]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://x.example.com/v1/systemone', $url);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-api-key', 'https://x.example.com/');
        $client->request(new Jev('jev-latest'), 'state', [
            'questions' => ['q' => ['type' => 'noul', 'instructions' => 'Yes?']],
        ]);
    }

    public function testItThrowsWhenQuestionsAreMissing()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "questions" option is required and must be a non-empty map of typed questions.');

        $client->request(new Jev('jev-latest'), 'state');
    }

    public function testItSupportsJevModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->assertTrue($client->supports(new Jev('jev-latest')));
    }
}

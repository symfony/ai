<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Brave\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Brave\Brave;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class BraveTest extends TestCase
{
    public function testReturnsSearchResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-results.json');
        $httpClient = new MockHttpClient($result);
        $brave = new Brave($httpClient, 'test-api-key');

        $results = $brave('latest Dallas Cowboys game result');

        $this->assertCount(5, $results);
        $this->assertArrayHasKey('title', $results[0]);
        $this->assertSame('Dallas Cowboys Scores, Stats and Highlights - ESPN', $results[0]['title']);
        $this->assertArrayHasKey('description', $results[0]);
        $this->assertSame('Visit ESPN for <strong>Dallas</strong> <strong>Cowboys</strong> live scores, video highlights, and <strong>latest</strong> news. Find standings and the full 2024 season schedule.', $results[0]['description']);
        $this->assertArrayHasKey('url', $results[0]);
        $this->assertSame('https://www.espn.com/nfl/team/_/name/dal/dallas-cowboys', $results[0]['url']);
    }

    public function testPassesCorrectParametersToApi()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-results.json');
        $httpClient = new MockHttpClient($result);
        $brave = new Brave($httpClient, 'test-api-key', ['extra' => 'option']);

        $brave('test query', 10, 5);

        $request = $result->getRequestUrl();
        $this->assertStringContainsString('q=test%20query', $request);
        $this->assertStringContainsString('count=10', $request);
        $this->assertStringContainsString('offset=5', $request);
        $this->assertStringContainsString('extra=option', $request);

        $requestOptions = $result->getRequestOptions();
        $this->assertArrayHasKey('headers', $requestOptions);
        $this->assertIsArray($requestOptions['headers']);
        $this->assertContains('X-Subscription-Token: test-api-key', $requestOptions['headers']);
    }

    public function testHandlesEmptyResults()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['web' => ['results' => []]]));
        $brave = new Brave($httpClient, 'test-api-key');

        $results = $brave('this should return nothing');

        $this->assertEmpty($results);
    }

    public function testHandlesMissingDescription()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'web' => [
                'results' => [
                    [
                        'title' => 'Test Title',
                        'url' => 'https://example.com',
                    ],
                ],
            ],
        ]));
        $brave = new Brave($httpClient, 'test-api-key');

        $results = $brave('test query');

        $this->assertCount(1, $results);
        $this->assertSame('Test Title', $results[0]['title']);
        $this->assertSame('', $results[0]['description']);
        $this->assertSame('https://example.com', $results[0]['url']);
    }
}

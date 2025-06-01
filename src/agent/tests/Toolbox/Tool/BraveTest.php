<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Tool\Brave;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(Brave::class)]
final class BraveTest extends TestCase
{
    #[Test]
    public function returnsSearchResults(): void
    {
        $response = $this->jsonMockResponseFromFile(__DIR__.'/fixtures/brave.json');
        $httpClient = new MockHttpClient($response);
        $brave = new Brave($httpClient, 'test-api-key');

        $results = $brave('latest Dallas Cowboys game result');

        self::assertCount(5, $results);
        self::assertArrayHasKey('title', $results[0]);
        self::assertSame('Dallas Cowboys Scores, Stats and Highlights - ESPN', $results[0]['title']);
        self::assertArrayHasKey('description', $results[0]);
        self::assertSame('Visit ESPN for <strong>Dallas</strong> <strong>Cowboys</strong> live scores, video highlights, and <strong>latest</strong> news. Find standings and the full 2024 season schedule.', $results[0]['description']);
        self::assertArrayHasKey('url', $results[0]);
        self::assertSame('https://www.espn.com/nfl/team/_/name/dal/dallas-cowboys', $results[0]['url']);
    }

    #[Test]
    public function passesCorrectParametersToApi(): void
    {
        $response = $this->jsonMockResponseFromFile(__DIR__.'/fixtures/brave.json');
        $httpClient = new MockHttpClient($response);
        $brave = new Brave($httpClient, 'test-api-key', ['extra' => 'option']);

        $brave('test query', 10, 5);

        $request = $response->getRequestUrl();
        self::assertStringContainsString('q=test%20query', $request);
        self::assertStringContainsString('count=10', $request);
        self::assertStringContainsString('offset=5', $request);
        self::assertStringContainsString('extra=option', $request);

        $requestOptions = $response->getRequestOptions();
        self::assertArrayHasKey('headers', $requestOptions);
        self::assertContains('X-Subscription-Token: test-api-key', $requestOptions['headers']);
    }

    #[Test]
    public function handlesEmptyResults(): void
    {
        $response = new MockResponse(json_encode(['web' => ['results' => []]]));
        $httpClient = new MockHttpClient($response);
        $brave = new Brave($httpClient, 'test-api-key');

        $results = $brave('this should return nothing');

        self::assertEmpty($results);
    }

    /**
     * This can be replaced by `JsonMockResponse::fromFile` when dropping Symfony 6.4.
     */
    private function jsonMockResponseFromFile(string $file): JsonMockResponse
    {
        return new JsonMockResponse(json_decode(file_get_contents($file), true));
    }
}

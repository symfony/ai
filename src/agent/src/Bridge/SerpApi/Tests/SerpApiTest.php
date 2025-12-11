<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\SerpApi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\SerpApi\SerpApi;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class SerpApiTest extends TestCase
{
    public function testReturnsSearchResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-results.json');
        $httpClient = new MockHttpClient($result);
        $serpApi = new SerpApi($httpClient, 'test-api-key');

        $results = $serpApi('symfony ai framework');

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('title', $results[0]);
        $this->assertSame('Symfony AI - Build AI-powered applications', $results[0]['title']);
        $this->assertArrayHasKey('link', $results[0]);
        $this->assertSame('https://symfony.com/ai', $results[0]['link']);
        $this->assertArrayHasKey('content', $results[0]);
        $this->assertSame('The Symfony AI component provides a unified interface to interact with various AI platforms and build intelligent applications.', $results[0]['content']);
    }

    public function testPassesCorrectParametersToApi()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-results.json');
        $httpClient = new MockHttpClient($result);
        $serpApi = new SerpApi($httpClient, 'test-api-key');

        $serpApi('test query');

        $request = $result->getRequestUrl();
        $this->assertStringContainsString('q=test%20query', $request);
        $this->assertStringContainsString('api_key=test-api-key', $request);
    }

    public function testHandlesEmptyResults()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['organic_results' => []]));
        $serpApi = new SerpApi($httpClient, 'test-api-key');

        $results = $serpApi('this should return nothing');

        $this->assertEmpty($results);
    }
}

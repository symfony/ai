<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Tavily\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Tavily\Tavily;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class TavilyTest extends TestCase
{
    public function testSearchReturnsResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-results.json');
        $httpClient = new MockHttpClient($result);
        $tavily = new Tavily($httpClient, 'test-api-key');

        $response = $tavily->search('latest AI news');

        $this->assertStringContainsString('results', $response);
    }

    public function testSearchPassesCorrectParameters()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-results.json');
        $httpClient = new MockHttpClient($result);
        $tavily = new Tavily($httpClient, 'test-api-key', ['include_images' => true]);

        $tavily->search('test query');

        $requestUrl = $result->getRequestUrl();
        $this->assertSame('https://api.tavily.com/search', $requestUrl);

        $requestOptions = $result->getRequestOptions();
        $this->assertArrayHasKey('body', $requestOptions);
        $body = json_decode($requestOptions['body'], true);
        $this->assertSame('test query', $body['query']);
        $this->assertSame('test-api-key', $body['api_key']);
        $this->assertTrue($body['include_images']);
    }

    public function testSearchAddsSourcesFromResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search-results.json');
        $httpClient = new MockHttpClient($result);
        $tavily = new Tavily($httpClient, 'test-api-key');

        $tavily->search('test query');

        $sources = $tavily->getSourceMap()->getSources();
        $this->assertCount(2, $sources);
        $this->assertSame('AI breakthrough announced', $sources[0]->getName());
        $this->assertSame('https://example.com/ai-news', $sources[0]->getReference());
    }

    public function testExtractReturnsResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/extract-results.json');
        $httpClient = new MockHttpClient($result);
        $tavily = new Tavily($httpClient, 'test-api-key');

        $response = $tavily->extract(['https://example.com/article']);

        $this->assertStringContainsString('results', $response);
    }

    public function testExtractPassesCorrectParameters()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/extract-results.json');
        $httpClient = new MockHttpClient($result);
        $tavily = new Tavily($httpClient, 'test-api-key');

        $urls = ['https://example.com/article1', 'https://example.com/article2'];
        $tavily->extract($urls);

        $requestUrl = $result->getRequestUrl();
        $this->assertSame('https://api.tavily.com/extract', $requestUrl);

        $requestOptions = $result->getRequestOptions();
        $this->assertArrayHasKey('body', $requestOptions);
        $body = json_decode($requestOptions['body'], true);
        $this->assertSame($urls, $body['urls']);
        $this->assertSame('test-api-key', $body['api_key']);
    }

    public function testExtractAddsSourcesFromResults()
    {
        $result = JsonMockResponse::fromFile(__DIR__.'/Fixtures/extract-results.json');
        $httpClient = new MockHttpClient($result);
        $tavily = new Tavily($httpClient, 'test-api-key');

        $tavily->extract(['https://example.com/article']);

        $sources = $tavily->getSourceMap()->getSources();
        $this->assertCount(1, $sources);
        $this->assertSame('Example Article', $sources[0]->getName());
        $this->assertSame('https://example.com/article', $sources[0]->getReference());
    }

    public function testHandlesEmptySearchResults()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['results' => []]));
        $tavily = new Tavily($httpClient, 'test-api-key');

        $tavily->search('query with no results');

        $sources = $tavily->getSourceMap()->getSources();
        $this->assertEmpty($sources);
    }

    public function testHandlesEmptyExtractResults()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['results' => []]));
        $tavily = new Tavily($httpClient, 'test-api-key');

        $tavily->extract(['https://nonexistent.com']);

        $sources = $tavily->getSourceMap()->getSources();
        $this->assertEmpty($sources);
    }
}

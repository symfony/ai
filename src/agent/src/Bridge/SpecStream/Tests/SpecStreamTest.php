<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\SpecStream\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\SpecStream\SpecStream;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class SpecStreamTest extends TestCase
{
    public function testSearchReturnsAnswerAndResults()
    {
        $httpClient = new MockHttpClient(JsonMockResponse::fromFile(__DIR__.'/Fixtures/search.json'));
        $specStream = new SpecStream($httpClient, 'test-api-key');

        $result = $specStream->search('who founded Symfony');

        $this->assertSame('Fabien Potencier', $result['answer']);
        $this->assertCount(2, $result['results']);
        $this->assertSame('History of Symfony', $result['results'][0]['title']);
        $this->assertSame('https://example.com/symfony-history', $result['results'][0]['url']);
        $this->assertSame('Symfony was created by Fabien Potencier in 2005.', $result['results'][0]['snippet']);
    }

    public function testSearchSendsTheQueryWithTheApiKey()
    {
        $response = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search.json');
        $httpClient = new MockHttpClient($response);
        $specStream = new SpecStream($httpClient, 'test-api-key');

        $specStream->search('who founded Symfony');

        $this->assertSame('GET', $response->getRequestMethod());
        $this->assertSame('https://api.specstream.ai/v1/web-search?q=who%20founded%20Symfony', $response->getRequestUrl());
        $this->assertContains('Authorization: Bearer test-api-key', $response->getRequestOptions()['headers']);
    }

    public function testSearchAddsSourcesFromResults()
    {
        $httpClient = new MockHttpClient(JsonMockResponse::fromFile(__DIR__.'/Fixtures/search.json'));
        $specStream = new SpecStream($httpClient, 'test-api-key');

        $specStream->search('who founded Symfony');

        $sources = $specStream->getSourceCollection()->all();
        $this->assertCount(2, $sources);
        $this->assertSame('History of Symfony', $sources[0]->getName());
        $this->assertSame('https://example.com/symfony-history', $sources[0]->getReference());
    }

    public function testSearchWithoutAnswerOrSources()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['data' => ['kind' => 'fact', 'answer' => null, 'sources' => []]]));
        $specStream = new SpecStream($httpClient, 'test-api-key');

        $result = $specStream->search('a question without an answer');

        $this->assertNull($result['answer']);
        $this->assertSame([], $result['results']);
        $this->assertEmpty($specStream->getSourceCollection()->all());
    }

    public function testExtractReturnsPages()
    {
        $httpClient = new MockHttpClient(JsonMockResponse::fromFile(__DIR__.'/Fixtures/extract.json'));
        $specStream = new SpecStream($httpClient, 'test-api-key');

        $pages = $specStream->extract(['https://example.com/article', 'https://example.com/missing']);

        $this->assertCount(2, $pages);
        $this->assertSame('ok', $pages[0]['status']);
        $this->assertSame('Example Article', $pages[0]['title']);
        $this->assertSame("# Example Article\n\nBody text.", $pages[0]['text']);
        $this->assertSame('not_found', $pages[1]['status']);
        $this->assertSame('', $pages[1]['text']);
    }

    public function testExtractSendsTheUrlsWithTheApiKey()
    {
        $response = JsonMockResponse::fromFile(__DIR__.'/Fixtures/extract.json');
        $httpClient = new MockHttpClient($response);
        $specStream = new SpecStream($httpClient, 'test-api-key', 'https://api.example.test');

        $urls = ['https://example.com/article', 'https://example.com/missing'];
        $specStream->extract($urls);

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://api.example.test/v1/extract', $response->getRequestUrl());
        $this->assertContains('Authorization: Bearer test-api-key', $response->getRequestOptions()['headers']);

        $body = json_decode($response->getRequestOptions()['body'], true);
        $this->assertSame($urls, $body['urls']);
        $this->assertSame('markdown', $body['format']);
    }

    public function testExtractAddsSourcesOnlyForPagesRead()
    {
        $httpClient = new MockHttpClient(JsonMockResponse::fromFile(__DIR__.'/Fixtures/extract.json'));
        $specStream = new SpecStream($httpClient, 'test-api-key');

        $specStream->extract(['https://example.com/article', 'https://example.com/missing']);

        $sources = $specStream->getSourceCollection()->all();
        $this->assertCount(1, $sources);
        $this->assertSame('Example Article', $sources[0]->getName());
        $this->assertSame('https://example.com/article', $sources[0]->getReference());
    }
}

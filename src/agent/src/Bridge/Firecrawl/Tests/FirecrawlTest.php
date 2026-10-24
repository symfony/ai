<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Firecrawl\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Firecrawl\Firecrawl;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class FirecrawlTest extends TestCase
{
    public function testSearch()
    {
        $response = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search.json');
        $httpClient = new MockHttpClient($response);

        $firecrawl = new Firecrawl($httpClient, 'test', 'https://127.0.0.1:3002');

        $results = $firecrawl->search('symfony messenger');

        $this->assertCount(3, $results);
        $this->assertSame([
            'title' => 'Messenger: Sync & Queued Message Handling (Symfony Docs)',
            'url' => 'https://symfony.com/doc/current/messenger.html',
            'description' => 'Messenger provides a message bus with the ability to send messages and then handle them immediately in your application or send them through transports (e.g. queues) to be handled later.',
        ], $results[0]);
        $this->assertSame(1, $httpClient->getRequestsCount());

        $sources = $firecrawl->getSourceCollection()->all();
        $this->assertCount(3, $sources);
        $this->assertSame('Messenger: Sync & Queued Message Handling (Symfony Docs)', $sources[0]->getName());
        $this->assertSame('https://symfony.com/doc/current/messenger.html', $sources[0]->getReference());
    }

    public function testSearchPassesCorrectParametersToApi()
    {
        $response = JsonMockResponse::fromFile(__DIR__.'/Fixtures/search.json');
        $httpClient = new MockHttpClient($response);

        $firecrawl = new Firecrawl($httpClient, 'test', 'https://127.0.0.1:3002');

        $firecrawl->search('symfony messenger', 3);

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://127.0.0.1:3002/v2/search', $response->getRequestUrl());

        $requestOptions = $response->getRequestOptions();
        $this->assertContains('Authorization: Bearer test', $requestOptions['headers']);
        $this->assertSame([
            'query' => 'symfony messenger',
            'limit' => 3,
            'sources' => ['web'],
            'origin' => 'symfony-ai',
        ], json_decode($requestOptions['body'], true));
    }

    public function testSearchHandlesEmptyResults()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['success' => true, 'data' => ['web' => []]]));

        $firecrawl = new Firecrawl($httpClient, 'test', 'https://127.0.0.1:3002');

        $this->assertSame([], $firecrawl->search('this should return nothing'));
        $this->assertCount(0, $firecrawl->getSourceCollection());
    }

    public function testSearchHandlesMissingTitleAndDescription()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'success' => true,
            'data' => [
                'web' => [
                    ['url' => 'https://example.com'],
                ],
            ],
        ]));

        $firecrawl = new Firecrawl($httpClient, 'test', 'https://127.0.0.1:3002');

        $results = $firecrawl->search('test query');

        $this->assertSame([['title' => '', 'url' => 'https://example.com', 'description' => '']], $results);
        $this->assertSame('https://example.com', $firecrawl->getSourceCollection()->all()[0]->getReference());
    }

    public function testScrape()
    {
        $httpClient = new MockHttpClient([
            JsonMockResponse::fromFile(__DIR__.'/Fixtures/scrape.json'),
        ]);

        $firecrawl = new Firecrawl($httpClient, 'test', 'https://127.0.0.1:3002');

        $scrapingResult = $firecrawl->scrape('https://www.symfony.com');

        $this->assertSame('https://www.symfony.com', $scrapingResult['url']);
        $this->assertNotEmpty($scrapingResult['markdown']);
        $this->assertNotEmpty($scrapingResult['html']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCrawl()
    {
        $httpClient = new MockHttpClient([
            JsonMockResponse::fromFile(__DIR__.'/Fixtures/crawl-wait.json'),
            JsonMockResponse::fromFile(__DIR__.'/Fixtures/crawl-status.json'),
            JsonMockResponse::fromFile(__DIR__.'/Fixtures/crawl-status-done.json'),
            JsonMockResponse::fromFile(__DIR__.'/Fixtures/crawl.json'),
        ]);

        $firecrawl = new Firecrawl($httpClient, 'test', 'https://127.0.0.1:3002');

        $scrapingResult = $firecrawl->crawl('https://www.symfony.com');

        $this->assertCount(1, $scrapingResult);
        $this->assertNotEmpty($scrapingResult[0]);

        $firstItem = $scrapingResult[0];
        $this->assertSame('https://www.symfony.com', $firstItem['url']);
        $this->assertNotEmpty($firstItem['markdown']);
        $this->assertNotEmpty($firstItem['html']);
        $this->assertSame(4, $httpClient->getRequestsCount());
    }

    public function testMap()
    {
        $httpClient = new MockHttpClient([
            JsonMockResponse::fromFile(__DIR__.'/Fixtures/map.json'),
        ]);

        $firecrawl = new Firecrawl($httpClient, 'test', 'https://127.0.0.1:3002');

        $mapping = $firecrawl->map('https://www.symfony.com');

        $this->assertSame('https://www.symfony.com', $mapping['url']);
        $this->assertCount(5, $mapping['links']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}

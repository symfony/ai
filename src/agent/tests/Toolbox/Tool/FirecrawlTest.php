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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Tool\Firecrawl;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class FirecrawlTest extends TestCase
{
    public function testScrape()
    {
        $httpClient = new MockHttpClient([
            JsonMockResponse::fromFile(__DIR__.'/../../fixtures/Tool/firecrawl-scrape.json'),
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
            JsonMockResponse::fromFile(__DIR__.'/../../fixtures/Tool/firecrawl-crawl-wait.json'),
            JsonMockResponse::fromFile(__DIR__.'/../../fixtures/Tool/firecrawl-crawl-status.json'),
            JsonMockResponse::fromFile(__DIR__.'/../../fixtures/Tool/firecrawl-crawl-status-done.json'),
            JsonMockResponse::fromFile(__DIR__.'/../../fixtures/Tool/firecrawl-crawl.json'),
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
            JsonMockResponse::fromFile(__DIR__.'/../../fixtures/Tool/firecrawl-map.json'),
        ]);

        $firecrawl = new Firecrawl($httpClient, 'test', 'https://127.0.0.1:3002');

        $mapping = $firecrawl->map('https://www.symfony.com');

        $this->assertSame('https://www.symfony.com', $mapping['url']);
        $this->assertCount(5, $mapping['links']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}

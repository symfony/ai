<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Scraper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Scraper\Scraper;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ScraperTest extends TestCase
{
    public function testInvoke()
    {
        $htmlContent = file_get_contents(__DIR__.'/Fixtures/page.html');
        $response = new MockResponse($htmlContent);
        $httpClient = new MockHttpClient($response);

        $scraper = new Scraper($httpClient);

        $result = $scraper('https://example.com');

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertSame('Example Page Title', $result['title']);
        $this->assertStringContainsString('Welcome to Example Page', $result['content']);
        $this->assertStringContainsString('This is some visible text content.', $result['content']);
    }

    public function testSourceIsAdded()
    {
        $htmlContent = file_get_contents(__DIR__.'/Fixtures/page.html');
        $response = new MockResponse($htmlContent);
        $httpClient = new MockHttpClient($response);

        $scraper = new Scraper($httpClient);

        $scraper('https://example.com');

        $sources = $scraper->getSourceMap()->getSources();
        $this->assertCount(1, $sources);
        $this->assertSame('Example Page Title', $sources[0]->getName());
        $this->assertSame('https://example.com', $sources[0]->getReference());
        $this->assertStringContainsString('Welcome to Example Page', $sources[0]->getContent());
    }
}

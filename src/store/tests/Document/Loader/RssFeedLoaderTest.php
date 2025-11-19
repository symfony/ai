<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Loader\RssFeedLoader;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @author Niklas Grießer <niklas@griesser.me>
 */
final class RssFeedLoaderTest extends TestCase
{
    public function testLoadWithNullSource()
    {
        $httpClient = new MockHttpClient([]);
        $loader = new RssFeedLoader($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%s" requires a URL as source, null given.', RssFeedLoader::class));

        iterator_to_array($loader->load(null));
    }

    public function testLoadWithValidRssFeed()
    {
        $httpClient = new MockHttpClient([MockResponse::fromFile(__DIR__.'/../../fixtures/symfony-blog.rss')]);
        $loader = new RssFeedLoader($httpClient);

        $documents = iterator_to_array($loader->load('https://feeds.feedburner.com/symfony/blog'));
        $this->assertCount(10, $documents);

        $firstDocument = $documents[0];
        $this->assertInstanceOf(TextDocument::class, $firstDocument);
        $this->assertStringStartsWith('Title: Save the date, SymfonyDay Montreal 2026!', $firstDocument->getContent());
        $this->assertStringContainsString('Date: 2025-09-11 14:30', $firstDocument->getContent());
        $this->assertStringContainsString('SymfonyDay Montreal is happening on', $firstDocument->getContent());

        $firstMetadata = $firstDocument->getMetadata();
        $this->assertSame('Save the date, SymfonyDay Montreal 2026!', $firstMetadata['title']);
        $this->assertSame('https://symfony.com/blog/save-the-date-symfonyday-montreal-2026?utm_source=Symfony%20Blog%20Feed&utm_medium=feed', $firstMetadata['link']);
        $this->assertSame('Paola Suárez', $firstMetadata['author']);
        $this->assertSame('2025-09-11 14:30', $firstMetadata['date']);
    }

    public function testLoadWithInvalidRssFeed()
    {
        $httpClient = new MockHttpClient([new MockResponse('not XML at all')]);
        $loader = new RssFeedLoader($httpClient);

        $documents = iterator_to_array($loader->load('https://feeds.feedburner.com/symfony/blog'));
        $this->assertCount(0, $documents);
    }

    public function testLoadWithHttpError()
    {
        $httpClient = new MockHttpClient([new MockResponse('Page not found', ['http_code' => 404])]);
        $loader = new RssFeedLoader($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch RSS feed/');

        iterator_to_array($loader->load('https://feeds.feedburner.com/symfony/blog'));
    }

    public function testLoadWithEmptyFeed()
    {
        $emptyFeedXml = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
    <channel>
        <title>Empty Feed</title>
        <link>https://example.com/</link>
        <description>An empty RSS feed</description>
    </channel>
</rss>
XML;

        $httpClient = new MockHttpClient([new MockResponse($emptyFeedXml)]);
        $loader = new RssFeedLoader($httpClient);

        $documents = iterator_to_array($loader->load('https://example.com/feed.xml'));
        $this->assertCount(0, $documents);
    }

    public function testLoadWithHttpClientException()
    {
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);
        $loader = new RssFeedLoader($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch RSS feed/');

        iterator_to_array($loader->load('https://example.com/non-existent-feed.xml'));
    }

    public function testLoadWithMalformedXml()
    {
        $httpClient = new MockHttpClient([new MockResponse('Not XML at all')]);
        $loader = new RssFeedLoader($httpClient);

        $documents = iterator_to_array($loader->load('https://example.com/malformed-feed.xml'));
        $this->assertCount(0, $documents);
    }

    public function testLoadReturnsIterableOfTextDocuments()
    {
        $httpClient = new MockHttpClient([MockResponse::fromFile(__DIR__.'/../../fixtures/symfony-blog.rss')]);
        $loader = new RssFeedLoader($httpClient);
        $result = $loader->load('https://feeds.feedburner.com/symfony/blog');

        foreach ($result as $document) {
            $this->assertInstanceOf(TextDocument::class, $document);
            $this->assertInstanceOf(Uuid::class, $document->getId());
            $this->assertNotEmpty($document->getContent());
        }
    }

    public function testLoadGeneratesConsistentUuids()
    {
        $httpClient = new MockHttpClient([MockResponse::fromFile(__DIR__.'/../../fixtures/symfony-blog.rss')]);
        $loader = new RssFeedLoader($httpClient);
        $documents1 = iterator_to_array($loader->load('https://feeds.feedburner.com/symfony/blog'));

        // Load same feed again
        $httpClient2 = new MockHttpClient([MockResponse::fromFile(__DIR__.'/../../fixtures/symfony-blog.rss')]);
        $loader2 = new RssFeedLoader($httpClient2);
        $documents2 = iterator_to_array($loader2->load('https://feeds.feedburner.com/symfony/blog'));

        $this->assertCount(10, $documents1);
        $this->assertCount(10, $documents2);

        // UUIDs should be identical for same content
        $this->assertEquals($documents1[0]->getId(), $documents2[0]->getId());
        $this->assertEquals($documents1[1]->getId(), $documents2[1]->getId());
    }
}

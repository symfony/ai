<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Document\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Loader\RssLoader;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Niklas Grießer <niklas@griesser.me>
 */
#[CoversClass(RssLoader::class)]
final class RssLoaderTest extends TestCase
{
    public function testLoadWithNullSource(): void
    {
        $loader = new RssLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Symfony\AI\Store\Document\Loader\RssLoader requires a URL as source, null given.');

        iterator_to_array($loader->load(null));
    }

    public function testLoadWithValidRssFeed(): void
    {
        $httpClient = new MockHttpClient([new MockResponse(file_get_contents(__DIR__.'/fixtures/blog.xml'))]);
        $loader = new RssLoader(httpClient: $httpClient);

        $documents = iterator_to_array($loader->load('https://feeds.feedburner.com/symfony/blog'));
        $this->assertCount(10, $documents);
        $this->assertInstanceOf(TextDocument::class, $document = $documents[0]);
        $this->assertStringStartsWith('Title: Save the date, SymfonyDay Montreal 2026!', $document->content);
    }

    public function testLoadWithInvalidRssFeed(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('not XML at all')]);
        $loader = new RssLoader(httpClient: $httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to parse RSS feed/');

        iterator_to_array($loader->load('https://feeds.feedburner.com/symfony/blog'));
    }

    public function testLoadWithHttpError(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('Page not found', ['http_code' => 404])]);
        $loader = new RssLoader(httpClient: $httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch RSS feed/');

        iterator_to_array($loader->load('https://feeds.feedburner.com/symfony/blog'));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Loader;

use Symfony\AI\Store\Document\Loader\Rss\RssItem;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Niklas Grießer <niklas@griesser.me>
 */
final readonly class RssFeedLoader implements LoaderInterface
{
    public const OPTION_UUID_NAMESPACE = 'uuid_namespace';

    /**
     * @param string $uuidNamespace The namespace used to generate stable identifiers using UUIDv5
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $uuidNamespace = '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
    ) {
    }

    /**
     * @param array{uuid_namespace?: string} $options
     */
    public function load(?string $source, array $options = []): iterable
    {
        if (!class_exists(Crawler::class)) {
            throw new RuntimeException('For using the RSS loader, the Symfony DomCrawler component is required. Try running "composer require symfony/dom-crawler".');
        }

        if (null === $source) {
            throw new InvalidArgumentException(\sprintf('"%s" requires a URL as source, null given.', self::class));
        }

        $uuidNamespace = Uuid::fromString($options[self::OPTION_UUID_NAMESPACE] ?? $this->uuidNamespace);

        try {
            $xml = $this->httpClient->request('GET', $source, [
                'headers' => [
                    'Accept' => 'application/rss+xml,application/xml,text/xml',
                ],
            ])->getContent();
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException(\sprintf('Failed to fetch RSS feed from "%s": "%s"', $source, $exception->getMessage()), previous: $exception);
        }

        $crawler = new Crawler($xml);
        foreach ($crawler->filterXpath('rss/channel/item') as $item) {
            $node = new Crawler($item);
            $guid = $node->filterXpath('node()/guid')->count() > 0 ? $node->filterXpath('node()/guid')->text() : null;
            $link = $node->filterXpath('node()/link')->text();
            $id = null !== $guid && Uuid::isValid($guid) ? Uuid::fromString($guid) : Uuid::v5($uuidNamespace, $guid ?? $link);
            $author = $node->filterXpath('node()/dc:creator')->count() > 0 ? $node->filterXpath('node()/dc:creator')->text() : null;
            $content = $node->filterXpath('node()/content:encoded')->count() > 0 ? $node->filterXpath('node()/content:encoded')->text() : null;

            $item = new RssItem($id, $node->filterXpath('//title')->text(), $link, new \DateTimeImmutable($node->filterXpath('//pubDate')->text()), $node->filterXpath('//description')->text(), $author, $content);

            yield new TextDocument($id, $item->toString(), new Metadata([
                Metadata::KEY_SOURCE => $source,
                ...$item->toArray(),
            ]));
        }
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Blog;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeedLoader
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return Post[]
     */
    public function load(): array
    {
        $result = $this->httpClient->request('GET', 'https://feeds.feedburner.com/symfony/blog');

        $posts = [];
        $crawler = new Crawler($result->getContent());
        $crawler->filter('item')->each(function (Crawler $node) use (&$posts) {
            $title = $node->filter('title')->text();
            $posts[] = new Post(
                Uuid::v5(Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'), $title),
                $title,
                $node->filter('link')->text(),
                $node->filter('description')->text(),
                (new Crawler($node->filter('content\:encoded')->text()))->text(),
                $node->filter('dc\:creator')->text(),
                new \DateTimeImmutable($node->filter('pubDate')->text()),
            );
        });

        return $posts;
    }
}

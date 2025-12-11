<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Scraper;

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('scraper', 'Loads the visible text and title of a website by URL.')]
final class Scraper implements HasSourcesInterface
{
    use HasSourcesTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        if (!class_exists(DomCrawler::class)) {
            throw new RuntimeException('For using the Scraper tool, the symfony/dom-crawler package is required. Try running "composer require symfony/dom-crawler".');
        }
    }

    /**
     * @param string $url the URL of the page to load data from
     *
     * @return array{title: string, content: string}
     */
    public function __invoke(string $url): array
    {
        $result = $this->httpClient->request('GET', $url);
        $crawler = new DomCrawler($result->getContent());

        $title = $crawler->filter('title')->text();
        $content = $crawler->filter('body')->text();

        $this->addSource(new Source($title, $url, $content));

        return [
            'title' => $title,
            'content' => $content,
        ];
    }
}

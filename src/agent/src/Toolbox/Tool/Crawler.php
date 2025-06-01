<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('crawler', 'A tool that crawls one page of a website and returns the visible text of it.')]
final readonly class Crawler
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
        if (!class_exists(DomCrawler::class)) {
            throw new RuntimeException('The DomCrawler component is not installed. Please install it using "composer require symfony/dom-crawler".');
        }
    }

    /**
     * @param string $url the URL of the page to crawl
     */
    public function __invoke(string $url): string
    {
        $response = $this->httpClient->request('GET', $url);

        return (new DomCrawler($response->getContent()))->filter('body')->text();
    }
}

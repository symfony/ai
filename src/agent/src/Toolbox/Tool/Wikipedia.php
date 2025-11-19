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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('wikipedia_search', description: 'Searches Wikipedia for a given query', method: 'search')]
#[AsTool('wikipedia_article', description: 'Retrieves a Wikipedia article by its title', method: 'article')]
final class Wikipedia implements HasSourcesInterface
{
    use HasSourcesTrait;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $locale = 'en',
    ) {
    }

    /**
     * @param string $query The query to search for on Wikipedia
     */
    public function search(string $query): string
    {
        $result = $this->execute([
            'action' => 'query',
            'format' => 'json',
            'list' => 'search',
            'srsearch' => $query,
        ], $this->locale);

        $titles = array_map(fn (array $item) => $item['title'], $result['query']['search']);

        if ([] === $titles) {
            return 'No articles were found on Wikipedia.';
        }

        $result = 'Articles with the following titles were found on Wikipedia:'.\PHP_EOL;
        foreach ($titles as $title) {
            $result .= ' - '.$title.\PHP_EOL;
        }

        return $result.\PHP_EOL.'Use the title of the article with tool "wikipedia_article" to load the content.';
    }

    /**
     * @param string $title The title of the article to load from Wikipedia
     */
    public function article(string $title): string
    {
        $response = $this->execute([
            'action' => 'query',
            'format' => 'json',
            'prop' => 'extracts|info|pageimages',
            'titles' => $title,
            'explaintext' => true,
            'redirects' => true,
        ], $this->locale);

        $article = current($response['query']['pages']);

        if (\array_key_exists('missing', $article)) {
            return \sprintf('No article with title "%s" was found on Wikipedia.', $title);
        }

        $result = '';
        if (\array_key_exists('redirects', $response['query'])) {
            foreach ($response['query']['redirects'] as $redirect) {
                $result .= \sprintf('The article "%s" redirects to article "%s".', $redirect['from'], $redirect['to']).\PHP_EOL;
            }
            $result .= \PHP_EOL;
        }

        $this->addSource(
            new Source($article['title'], $this->getUrl($article['title']), $article['extract'])
        );

        return $result.'This is the content of article "'.$article['title'].'":'.\PHP_EOL.$article['extract'];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function execute(array $query, ?string $locale = null): array
    {
        $url = \sprintf('https://%s.wikipedia.org/w/api.php', $locale ?? $this->locale);
        $response = $this->httpClient->request('GET', $url, ['query' => $query]);

        return $response->toArray();
    }

    private function getUrl(string $title): string
    {
        return \sprintf('https://%s.wikipedia.org/wiki/%s', $this->locale, str_replace(' ', '_', $title));
    }
}

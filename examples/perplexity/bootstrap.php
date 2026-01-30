<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once dirname(__DIR__).'/bootstrap.php';

/**
 * @param array<int, array<string, string>> $searchResults
 */
function print_search_results(array $searchResults): void
{
    echo 'Search results:'.\PHP_EOL;

    if (0 === count($searchResults)) {
        echo 'No search results.'.\PHP_EOL;

        return;
    }

    foreach ($searchResults as $i => $searchResult) {
        echo 'Result #'.($i + 1).':'.\PHP_EOL;
        echo isset($searchResult['title']) ? ' Title: '.$searchResult['title'].\PHP_EOL : '';
        echo isset($searchResult['url']) ? ' URL: '.$searchResult['url'].\PHP_EOL : '';
        echo isset($searchResult['date']) ? ' Date: '.$searchResult['date'].\PHP_EOL : '';
        echo isset($searchResult['last_updated']) ? ' Last Updated: '.$searchResult['last_updated'].\PHP_EOL : '';
        echo isset($searchResult['snippet']) ? ' Snippet: '.$searchResult['snippet'].\PHP_EOL : '';
        echo \PHP_EOL;
    }
}

/**
 * @param array<int, string> $citations
 */
function print_citations(array $citations): void
{
    echo 'Citations:'.\PHP_EOL;

    if (0 === count($citations)) {
        echo 'No citations.'.\PHP_EOL;

        return;
    }

    foreach ($citations as $i => $citation) {
        echo 'Citation #'.($i + 1).':'.\PHP_EOL;
        echo ' '.$citation.\PHP_EOL;
        echo \PHP_EOL;
    }
}

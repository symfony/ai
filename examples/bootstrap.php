<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

require_once __DIR__.'/vendor/autoload.php';
(new Dotenv())->loadEnv(__DIR__.'/.env');

function env(string $var): string
{
    if (!isset($_SERVER[$var]) || '' === $_SERVER[$var]) {
        printf('Please set the "%s" environment variable to run this example.', $var);
        exit(1);
    }

    return $_SERVER[$var];
}

function http_client(): HttpClientInterface
{
    $httpClient = HttpClient::create();

    if ($httpClient instanceof LoggerAwareInterface) {
        $httpClient->setLogger(logger());
    }

    return $httpClient;
}

function logger(): LoggerInterface
{
    $verbosity = match ($_SERVER['argv'][1] ?? null) {
        '-v', '--verbose' => ConsoleOutput::VERBOSITY_VERBOSE,
        '-vv', '--very-verbose' => ConsoleOutput::VERBOSITY_VERY_VERBOSE,
        '-vvv', '--debug' => ConsoleOutput::VERBOSITY_DEBUG,
        default => ConsoleOutput::VERBOSITY_NORMAL,
    };

    return new ConsoleLogger(new ConsoleOutput($verbosity));
}

function print_token_usage(Metadata $metadata): void
{
    $tokenUsage = $metadata->get('token_usage');

    assert($tokenUsage instanceof TokenUsage);

    echo 'Prompt tokens: '.$tokenUsage->promptTokens.\PHP_EOL;
    echo 'Completion tokens: '.$tokenUsage->completionTokens.\PHP_EOL;
    echo 'Thinking tokens: '.$tokenUsage->thinkingTokens.\PHP_EOL;
    echo 'Tool tokens: '.$tokenUsage->toolTokens.\PHP_EOL;
    echo 'Cached tokens: '.$tokenUsage->cachedTokens.\PHP_EOL;
    echo 'Remaining tokens minute: '.$tokenUsage->remainingTokensMinute.\PHP_EOL;
    echo 'Remaining tokens month: '.$tokenUsage->remainingTokensMonth.\PHP_EOL;
    echo 'Remaining tokens: '.$tokenUsage->remainingTokens.\PHP_EOL;
    echo 'Utilized tokens: '.$tokenUsage->totalTokens.\PHP_EOL;
}

function print_vectors(ResultPromise $result): void
{
    assert([] !== $result->asVectors());
    assert(array_key_exists(0, $result->asVectors()));

    echo 'Dimensions: '.$result->asVectors()[0]->getDimensions().\PHP_EOL;
}

function perplexity_print_search_results(Metadata $metadata): void
{
    $searchResults = $metadata->get('search_results');

    if (null === $searchResults) {
        return;
    }

    echo 'Search results:'.\PHP_EOL;

    if (0 === count($searchResults)) {
        echo 'No search results.'.\PHP_EOL;

        return;
    }

    foreach ($searchResults as $i => $searchResult) {
        echo 'Result #'.($i + 1).':'.\PHP_EOL;
        echo $searchResult['title'].\PHP_EOL;
        echo $searchResult['url'].\PHP_EOL;
        echo $searchResult['date'].\PHP_EOL;
        echo $searchResult['last_updated'] ? $searchResult['last_updated'].\PHP_EOL : '';
        echo $searchResult['snippet'] ? $searchResult['snippet'].\PHP_EOL : '';
        echo \PHP_EOL;
    }
}

function perplexity_print_citations(Metadata $metadata): void
{
    $citations = $metadata->get('citations');

    if (null === $citations) {
        return;
    }

    echo 'Citations:'.\PHP_EOL;

    if (0 === count($citations)) {
        echo 'No citations.'.\PHP_EOL;

        return;
    }

    foreach ($citations as $i => $citation) {
        echo 'Citation #'.($i + 1).':'.\PHP_EOL;
        echo $citation.\PHP_EOL;
        echo \PHP_EOL;
    }
}

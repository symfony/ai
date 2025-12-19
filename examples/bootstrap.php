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
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Store\Exception\ExceptionInterface as StoreException;
use Symfony\Component\Console\Helper\Table;
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
        output()->writeln(sprintf('<error>Please set the "%s" environment variable to run this example.</error>', $var));
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
    $output = output();

    return new class($output) extends ConsoleLogger {
        private ConsoleOutput $output;

        public function __construct(ConsoleOutput $output)
        {
            parent::__construct($output);
            $this->output = $output;
        }

        /**
         * @param Stringable|string $message
         */
        public function log($level, $message, array $context = []): void
        {
            // Call parent to handle the base logging
            parent::log($level, $message, $context);

            // Add context display for debug verbosity
            if ($this->output->getVerbosity() >= ConsoleOutput::VERBOSITY_DEBUG && [] !== $context) {
                // Filter out special keys that are already handled
                $displayContext = array_filter($context, function ($key) {
                    return !in_array($key, ['exception', 'error', 'object'], true);
                }, \ARRAY_FILTER_USE_KEY);

                if ([] !== $displayContext) {
                    $contextMessage = '  '.json_encode($displayContext, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
                    $this->output->writeln(sprintf('<comment>%s</comment>', $contextMessage));
                }
            }
        }
    };
}

function output(): ConsoleOutput
{
    $verbosity = match ($_SERVER['argv'][1] ?? null) {
        '-v', '--verbose' => ConsoleOutput::VERBOSITY_VERBOSE,
        '-vv', '--very-verbose' => ConsoleOutput::VERBOSITY_VERY_VERBOSE,
        '-vvv', '--debug' => ConsoleOutput::VERBOSITY_DEBUG,
        default => ConsoleOutput::VERBOSITY_NORMAL,
    };

    return new ConsoleOutput($verbosity);
}

function print_token_usage(Metadata $metadata): void
{
    $tokenUsage = $metadata->get('token_usage');

    if (!$tokenUsage instanceof TokenUsage) {
        output()->writeln('<error>No token usage information available.</error>');
        exit(1);
    }

    $na = '<comment>n/a</comment>';
    $table = new Table(output());
    $table->setHeaderTitle('Token Usage');
    $table->setRows([
        ['Prompt tokens', $tokenUsage->getPromptTokens() ?? $na],
        ['Completion tokens', $tokenUsage->getCompletionTokens() ?? $na],
        ['Thinking tokens', $tokenUsage->getThinkingTokens() ?? $na],
        ['Tool tokens', $tokenUsage->getToolTokens() ?? $na],
        ['Cached tokens', $tokenUsage->getCachedTokens() ?? $na],
        ['Remaining tokens minute', $tokenUsage->getRemainingTokensMinute() ?? $na],
        ['Remaining tokens month', $tokenUsage->getRemainingTokensMonth() ?? $na],
        ['Remaining tokens', $tokenUsage->getRemainingTokens() ?? $na],
        ['Total tokens', $tokenUsage->getTotalTokens() ?? $na],
    ]);
    $table->render();
}

function print_vectors(DeferredResult $result): void
{
    assert([] !== $result->asVectors());
    assert(array_key_exists(0, $result->asVectors()));

    output()->writeln(sprintf('Dimensions: %d', $result->asVectors()[0]->getDimensions()));
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
        echo isset($searchResult['title']) ? ' Title: '.$searchResult['title'].\PHP_EOL : '';
        echo isset($searchResult['url']) ? ' URL: '.$searchResult['url'].\PHP_EOL : '';
        echo isset($searchResult['date']) ? ' Date: '.$searchResult['date'].\PHP_EOL : '';
        echo isset($searchResult['last_updated']) ? ' Last Updated: '.$searchResult['last_updated'].\PHP_EOL : '';
        echo isset($searchResult['snippet']) ? ' Snippet: '.$searchResult['snippet'].\PHP_EOL : '';
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
        echo ' '.$citation.\PHP_EOL;
        echo \PHP_EOL;
    }
}

function print_stream(DeferredResult $result): void
{
    foreach ($result->asStream() as $word) {
        echo $word;
    }
    echo \PHP_EOL;
}

set_exception_handler(function ($exception) {
    if ($exception instanceof AgentException || $exception instanceof PlatformException || $exception instanceof StoreException) {
        output()->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

        if (output()->isVerbose()) {
            output()->writeln($exception->getTraceAsString());
        }

        exit(1);
    }

    throw $exception;
});

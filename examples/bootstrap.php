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
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

require_once __DIR__.'/vendor/autoload.php';
(new Dotenv())->loadEnv(__DIR__.'/.env');

function env(string $var)
{
    if (!isset($_SERVER[$var])) {
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

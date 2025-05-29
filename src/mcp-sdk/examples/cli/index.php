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

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console as SymfonyConsole;
use Symfony\Component\Console\Output\OutputInterface;

$debug = (bool) ($_SERVER['DEBUG'] ?? false);

// Setup input, output and logger
$input = new SymfonyConsole\Input\ArgvInput($argv);
$output = new SymfonyConsole\Output\ConsoleOutput($debug ? OutputInterface::VERBOSITY_VERY_VERBOSE : OutputInterface::VERBOSITY_NORMAL);
$logger = new SymfonyConsole\Logger\ConsoleLogger($output);

// Configure the JsonRpcHandler and build the functionality
$jsonRpcHandler = new PhpLlm\McpSdk\Server\JsonRpcHandler(
    new PhpLlm\McpSdk\Message\Factory(),
    App\Builder::buildRequestHandlers(),
    App\Builder::buildNotificationHandlers(),
    $logger
);

// Set up the server
$sever = new PhpLlm\McpSdk\Server($jsonRpcHandler, $logger);

// Create the transport layer using Symfony Console
$transport = new PhpLlm\McpSdk\Server\Transport\Stdio\SymfonyConsoleTransport($input, $output);

// Start our application
$sever->connect($transport);

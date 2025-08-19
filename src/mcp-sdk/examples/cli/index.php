<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require __DIR__.'/vendor/autoload.php';

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console as SymfonyConsole;
use Symfony\Component\Console\Output\OutputInterface;

$debug = (bool) ($_SERVER['DEBUG'] ?? false);

// Setup input, output
$input = new SymfonyConsole\Input\ArgvInput($argv);
$output = new SymfonyConsole\Output\ConsoleOutput($debug ? OutputInterface::VERBOSITY_VERY_VERBOSE : OutputInterface::VERBOSITY_NORMAL);

// Setup *Monolog* logger, logger must output JSON to stream to STDERR
// **WARNING** this will work only on the development stage with @modelcontextprotocol/inspector, but not in a real client
// For real clients please consider using other types of logs (file, client logging, etc.)
$handler = new StreamHandler('php://stderr');
$handler->setFormatter(new JsonFormatter());
$logger = new Logger('mcp', [$handler]);

// Configure the JsonRpcHandler and build the functionality
$jsonRpcHandler = new Symfony\AI\McpSdk\Server\JsonRpcHandler(
    new Symfony\AI\McpSdk\Message\Factory(),
    App\Builder::buildRequestHandlers(),
    App\Builder::buildNotificationHandlers(),
    $logger
);

// Set up the server
$sever = new Symfony\AI\McpSdk\Server($jsonRpcHandler, $logger);

// Create the transport layer using Symfony Console
$transport = new Symfony\AI\McpSdk\Server\Transport\Stdio\SymfonyConsoleTransport($input, $output);

// Start our application
$sever->connect($transport);

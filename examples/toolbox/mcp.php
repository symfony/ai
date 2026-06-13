<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Mcp\Client;
use Mcp\Client\Transport\StdioTransport;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Bridge\Mcp\ClientToolset;
use Symfony\AI\Agent\Bridge\Mcp\McpToolAdapter;
use Symfony\AI\Agent\Bridge\Mcp\McpToolFactory;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// Any stdio-based MCP server works - point MCP_SERVER_CMD at the one you want to expose.
$command = preg_split('/\s+/', trim(env('MCP_SERVER_CMD'))) ?: [];
$program = array_shift($command);

if (null === $program) {
    echo 'MCP_SERVER_CMD is empty.'.\PHP_EOL;
    exit(1);
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$toolset = new ClientToolset(
    env('MCP_SERVER_NAME'),
    Client::builder()->setClientInfo('symfony-ai-example', '0.1')->build(),
    new StdioTransport($program, $command),
);

$toolbox = new Toolbox([new McpToolAdapter($toolset)], new McpToolFactory(), logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox);

$messages = new MessageBag(
    Message::forSystem('Use the available MCP tools to answer the user.'),
    Message::ofUser('List the tools you have access to and briefly describe each one.'),
);

echo $agent->call($messages)->asText().\PHP_EOL;

$toolset->disconnect();

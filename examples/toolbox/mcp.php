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
use Symfony\AI\Agent\Bridge\Mcp\McpToolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

// Any stdio-based MCP server works, this one exposes the files below /tmp.
$toolset = new ClientToolset(
    'filesystem',
    Client::builder()->setClientInfo('symfony-ai-example', '0.1')->build(),
    new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-filesystem', '/tmp']),
);

$agent = new Agent($platform, 'gpt-5-mini', toolbox: new McpToolbox($toolset, logger: logger()));

$messages = new MessageBag(
    Message::forSystem('Use the available MCP tools to answer the user.'),
    Message::ofUser('List the tools you have access to and briefly describe each one.'),
);

echo $agent->call($messages)->asText().\PHP_EOL;

$toolset->disconnect();

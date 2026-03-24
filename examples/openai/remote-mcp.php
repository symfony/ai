<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Gpt\McpTool;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$mcpTool = new McpTool(
    serverLabel: 'dmcp',
    serverUrl: 'https://dmcp-server.deno.dev/sse',
    requireApproval: 'never',
    allowedTools: ['roll'],
);

$messages = new MessageBag(
    Message::ofUser('Roll a six-sided die for me.'),
);

$result = $platform->invoke('gpt-4o-mini', $messages, [
    'tools' => [$mcpTool],
]);

echo $result->asText().\PHP_EOL;

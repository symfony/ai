<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$agent = new Agent($platform, 'claude-sonnet-4-5-20250929');

$serverTools = ['web_search' => ['max_uses' => 2]];

$messages = new MessageBag(
    Message::ofUser('Which PHP version is the latest stable release? Search the web.'),
);

$execution = $agent->call($messages, ['server_tools' => $serverTools]);
$messages->add($assistant = Message::ofAssistant($execution->getResult()));

output()->writeln('<info>====== Turn 1 ======</info>');

// Anthropic splits the answer into several text blocks around its citations.
$answer = '';
foreach ($assistant->getContent() as $part) {
    if ($part instanceof WebSearch) {
        output()->writeln(sprintf('<comment>Search:</comment> %s (%s)', $part->getQuery() ?? '-', $part->getStatus() ?? '-'));
    } elseif ($part instanceof Text) {
        $answer .= $part->getText();
    }
}

output()->writeln('<comment>Assistant:</comment> '.$answer);

echo \PHP_EOL;

// Anthropic takes a web search back only as the pair of blocks it sent, the `server_tool_use`
// call and its `web_search_tool_result`, and rejects either one on its own. Turn 2 can name the
// source because the assistant turn replayed both.
output()->writeln('<info>====== Turn 2 ======</info>');
$messages->add(Message::ofUser('Which website did you take that from? Answer with the domain only.'));
$execution = $agent->call($messages, ['server_tools' => $serverTools]);
output()->writeln('<comment>Assistant:</comment> '.$execution->asText());

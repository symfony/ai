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
use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$wikipedia = new Wikipedia(http_client());
$toolbox = new Toolbox([$wikipedia], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'claude-sonnet-4-5-20250929', [$processor], [$processor]);
$messages = new MessageBag(Message::ofUser(<<<TXT
        First, define unicorn in 30 words.
        Then lookup at Wikipedia what the irish history looks like in 2 sentences.
        Please tell me before you call tools.
    TXT));
$result = $agent->call($messages, [
    'stream' => true, // enable streaming of response text
    'thinking' => [
        'type' => 'enabled',
        'budget_tokens' => 10000,
    ],
]);

foreach ($result->getContent() as $delta) {
    if ($delta instanceof ThinkingStart) {
        output()->writeln('<info><thinking></info>');
    }
    if ($delta instanceof ThinkingDelta) {
        output()->write('<fg=#999999>'.$delta->getThinking().'</>');
    }
    if ($delta instanceof ThinkingComplete) {
        output()->writeln(\PHP_EOL.'<info></thinking></info>');
    }
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}

echo \PHP_EOL;

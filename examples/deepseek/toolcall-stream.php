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
use Symfony\AI\Platform\Bridge\DeepSeek\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('DEEPSEEK_API_KEY'), http_client());

$wikipedia = new Wikipedia(http_client());
$toolbox = new Toolbox([$wikipedia]);
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'deepseek-chat', [$processor], [$processor]);
$messages = new MessageBag(Message::ofUser(<<<TXT
        First, define unicorn in 30 words.
        Then lookup at Wikipedia what the irish history looks like in 2 sentences.
        Please tell me before you call tools.
    TXT));
$result = $agent->call($messages, [
    'stream' => true, // enable streaming of response text
]);

foreach ($result->getContent() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}

echo \PHP_EOL;

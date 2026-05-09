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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

$toolbox = new Toolbox([], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gemini-3.1-pro-preview', [$processor], [$processor]);

$options = ['server_tools' => ['code_execution' => true]];

$messages = new MessageBag(
    Message::ofUser('Compute the 10th Fibonacci number using a short Python snippet.'),
);

$result = $agent->call($messages, $options);
$messages->add($assistant = Message::ofAssistant($result));

output()->writeln('<info>====== Turn 1 ======</info>');
foreach ($assistant->getContent() as $part) {
    if ($part instanceof Text) {
        output()->writeln('<comment>Assistant:</comment> '.$part->getText());
    } elseif ($part instanceof ExecutableCode) {
        output()->writeln('<comment>Code:</comment> '.\PHP_EOL.$part->getCode());
    } elseif ($part instanceof CodeExecution) {
        output()->writeln('<comment>Result:</comment> '.$part->getOutput());
    }
}

echo \PHP_EOL;

output()->writeln('<info>====== Turn 2 ======</info>');
$messages->add(Message::ofUser('What number did your snippet print? Answer with the number only.'));
$result = $agent->call($messages, $options);
output()->writeln('<comment>Assistant:</comment> '.$result->getContent());

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
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\Clock\Clock;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$metadataFactory = (new MemoryToolFactory())
    ->addTool(Clock::class, 'clock', 'Get the current date and time', 'now');
$toolbox = new Toolbox([new Clock()], $metadataFactory, logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('What date and time is it? Answer in one sentence.'));

// call() returns a lazy execution: every step the agent takes is yielded as an update
foreach ($agent->call($messages, ['stream' => true]) as $update) {
    if ($update instanceof Progress) {
        echo match ($update->getStage()) {
            // the answer is streamed token by token
            'delta' => $update->getPayload() instanceof TextDelta ? $update->getPayload()->getText() : '',
            'tool_call' => \PHP_EOL.'>> '.$update->getMessage().\PHP_EOL,
            default => \PHP_EOL.'>> '.$update->getMessage().\PHP_EOL,
        };
    }

    if ($update instanceof Result) {
        echo \PHP_EOL.\PHP_EOL.'Final result: '.$update->getResult()->getContent().\PHP_EOL;
    }
}

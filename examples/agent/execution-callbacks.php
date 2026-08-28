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
use Symfony\Component\Clock\Clock;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$metadataFactory = (new MemoryToolFactory())
    ->addTool(Clock::class, 'clock', 'Get the current date and time', 'now');
$toolbox = new Toolbox([new Clock()], $metadataFactory, logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('What date and time is it? Answer in one sentence.'));

// call() returns a lazy execution: callbacks observe the run on the side, nothing happens until it is consumed
$execution = $agent->call($messages)
    ->onProgress(static function (Progress $progress): void {
        echo '>> '.$progress->getMessage().\PHP_EOL;
    })
    ->onResult(static function (Result $result): void {
        echo '>> Agent finished.'.\PHP_EOL;
    });

// reading the result drives the execution to completion, invoking the callbacks along the way
echo \PHP_EOL.$execution->asText().\PHP_EOL;

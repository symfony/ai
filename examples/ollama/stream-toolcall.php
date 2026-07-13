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
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform('http://localhost:11434', env('OLLAMA_API_KEY'), httpClient: http_client());

$toolbox = new Toolbox([new Clock(clock())], logger: logger());
$agent = new Agent($platform, 'llama3.2', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('What time is it?'));

$result = $agent->call($messages, options: ['stream' => true]);

foreach ($result->getContent() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}

echo \PHP_EOL;

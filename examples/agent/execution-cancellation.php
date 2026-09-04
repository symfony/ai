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
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

if (!function_exists('pcntl_signal')) {
    echo 'This example requires the pcntl extension.'.\PHP_EOL;

    exit(1);
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
$agent = new Agent($platform, 'gpt-5-mini');

$messages = new MessageBag(Message::ofUser('Tell me a long story about a lighthouse keeper.'));

$execution = $agent->call($messages, ['stream' => true]);

// Enable async signal handling and register a signal handler for SIGINT (Ctrl+C)
pcntl_async_signals(true);
pcntl_signal(\SIGINT, static fn () => $execution->cancel());

$characters = 0;
foreach ($execution->asTextStream() as $delta) {
    echo $delta->getText();

    // Cancel the execution if the total number of characters exceeds 200
    if (($characters += strlen($delta->getText())) > 200) {
        $execution->cancel();
    }
}

echo \PHP_EOL.\PHP_EOL.'>> Canceled after '.$characters.' characters.'.\PHP_EOL;

try {
    $execution->getResult();
} catch (RuntimeException $e) {
    echo '>> '.$e->getMessage().\PHP_EOL;
}

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
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OLLAMA_HOST_URL'), http_client(), cache: new ArrayAdapter());
$model = new Ollama();

$agent = new Agent($platform, $model, logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
);
$result = $agent->call($messages, [
    'prompt_cache_key' => 'chat',
]);

echo $result->getContent().\PHP_EOL;

assert($result->getMetadata()->get('cached'));
assert('chat' === $result->getMetadata()->get('prompt_cache_key'));

$secondResult = $agent->call($messages, [
    'prompt_cache_key' => 'chat',
]);

echo $secondResult->getContent().\PHP_EOL;

assert($secondResult->getMetadata()->get('cached'));
assert('chat' === $secondResult->getMetadata()->get('prompt_cache_key'));

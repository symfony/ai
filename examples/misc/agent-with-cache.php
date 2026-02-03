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
use Symfony\AI\Platform\Bridge\Cache\CachePlatform;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$cachedPlatform = new CachePlatform($platform, cache: new TagAwareAdapter(new ArrayAdapter()));

$agent = new Agent($cachedPlatform, 'gpt-5-mini');
$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
);
$result = $agent->call($messages, [
    'prompt_cache_key' => 'chat',
]);

assert($result->getMetadata()->has('cached'));

echo $result->getContent().\PHP_EOL;

// Thanks to the cache adapter and the "prompt_cache_key" key, this call will not trigger any network call

$secondResult = $agent->call($messages, [
    'prompt_cache_key' => 'chat',
]);

assert($secondResult->getMetadata()->has('cached'));

echo $secondResult->getContent().\PHP_EOL;

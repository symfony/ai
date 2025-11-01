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
use Symfony\AI\Chat\Bridge\Redis\MessageStore;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$redis = new Redis([
    'host' => env('REDIS_HOST'),
    'port' => 6379,
]);

$store = new MessageStore($redis, 'symfony', new Serializer([
    new ArrayDenormalizer(),
    new MessageNormalizer(),
], [
    new JsonEncoder(),
]));
$store->setup();

$agent = new Agent($platform, 'gpt-4o-mini');
$chat = new Chat($agent, $store);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
);

$chat->initiate($messages);
$chat->submit(Message::ofUser('My name is Christopher.'));
$message = $chat->submit(Message::ofUser('What is my name?'));

echo $message->getContent().\PHP_EOL;

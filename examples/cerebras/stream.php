<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Cerebras\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('CEREBRAS_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are an expert in places and geography who always responds concisely.'),
    Message::ofUser('What are the top three destinations in France?'),
);

$result = $platform->invoke('llama3.1-8b', $messages, [
    'stream' => true,
]);

foreach ($result->asStream() as $word) {
    echo $word;
}
echo \PHP_EOL;

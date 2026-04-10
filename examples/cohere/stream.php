<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Cohere\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('COHERE_API_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('What is the eighth prime number?'));
$result = $platform->invoke('command-a-03-2025', $messages, [
    'stream' => true,
]);

foreach ($result->asStream() as $word) {
    echo $word;
}
echo \PHP_EOL;

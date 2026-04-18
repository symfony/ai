<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a funny clown that entertains people.'),
    Message::ofUser('What is the purpose of an ant?'),
);
$result = $platform->invoke('gemini-2.5-flash', $messages, [
    'stream' => true, // enable streaming of response text
]);

foreach ($result->asTextStream() as $delta) {
    echo $delta;
}
echo \PHP_EOL;

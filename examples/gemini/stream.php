<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a funny clown that entertains people.'),
    Message::ofUser('What is the purpose of an ant?'),
);
$result = $platform->invoke('gemini-2.0-flash', $messages, [
    'stream' => true, // enable streaming of response text
]);

foreach ($result->asStream() as $word) {
    echo $word;
}
echo \PHP_EOL;

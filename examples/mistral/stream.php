<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('MISTRAL_API_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('What is the eighth prime number?'));
$result = $platform->invoke('mistral-large-latest', $messages, [
    'stream' => true,
]);

foreach ($result->asTextStream() as $delta) {
    echo $delta;
}
echo \PHP_EOL;

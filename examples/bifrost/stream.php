<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bifrost\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('BIFROST_API_KEY'), env('BIFROST_ENDPOINT'), http_client());

$messages = new MessageBag(Message::ofUser('List the first 25 prime numbers, one per line.'));
$result = $platform->invoke('openai/gpt-4o-mini', $messages, [
    'stream' => true,
]);

foreach ($result->asTextStream() as $delta) {
    echo $delta;
}
echo \PHP_EOL;

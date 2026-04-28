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
use Symfony\AI\Platform\Bridge\VertexAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

require_once __DIR__.'/bootstrap.php';

$platform = Factory::createPlatform(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), httpClient: adc_aware_http_client());

$agent = new Agent($platform, 'gemini-2.5-flash');
$messages = new MessageBag(
    Message::forSystem('You are an expert assistant in animal study.'),
    Message::ofUser('What does a cat usually eat?'),
);
$result = $agent->call($messages, [
    'stream' => true,
]);

foreach ($result->getContent() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}

echo \PHP_EOL;

print_token_usage($result->getMetadata()->get('token_usage'));

echo \PHP_EOL;

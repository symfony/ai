<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new GPT(GPT::GPT_4O_MINI, [
    'temperature' => 0.5, // default options for the model
]);

$messages = new MessageBag(
    Message::forSystem('You will be given a letter and you answer with only the next letter of the alphabet.'),
);

echo 'Initiating parallel calls to GPT on platform ...'.\PHP_EOL;
$responses = [];
foreach (range('A', 'D') as $letter) {
    echo ' - Request for the letter '.$letter.' initiated.'.\PHP_EOL;
    $responses[] = $platform->request($model, $messages->with(Message::ofUser($letter)));
}

echo 'Waiting for the responses ...'.\PHP_EOL;
foreach ($responses as $response) {
    echo 'Next Letter: '.$response->asText().\PHP_EOL;
}

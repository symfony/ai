<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You will be given a letter and you answer with only the next letter of the alphabet.'),
);

echo 'Initiating parallel calls to GPT on platform ...'.\PHP_EOL;
$results = [];
foreach (range('A', 'D') as $letter) {
    echo ' - Request for the letter '.$letter.' initiated.'.\PHP_EOL;
    $results[] = $platform->invoke('gpt-4o-mini', $messages->with(Message::ofUser($letter)));
}

echo 'Waiting for the responses ...'.\PHP_EOL;
foreach ($results as $result) {
    echo 'Next Letter: '.$result->asText().\PHP_EOL;
}

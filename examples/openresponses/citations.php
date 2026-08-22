<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenResponses\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform('https://api.x.ai', env('XAI_API_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('What is xAI?'));
$result = $platform->invoke('grok-4-fast', $messages, [
    'tools' => [['type' => 'web_search']],
]);

echo $result->asText().\PHP_EOL;
echo \PHP_EOL;

$converted = $result->getResult();
$parts = $converted instanceof MultiPartResult ? $converted->getContent() : [$converted];

$citations = [];
foreach ($parts as $part) {
    if ($part instanceof TextResult) {
        $citations = $part->getMetadata()->get('citations') ?? [];
    }
}

echo 'Citations:'.\PHP_EOL;
if ([] === $citations) {
    echo 'No citations.'.\PHP_EOL;
} else {
    foreach ($citations as $citation) {
        echo ' '.$citation.\PHP_EOL;
    }
}

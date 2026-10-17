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

// A batch is submitted like any other invocation, keyed by the identifier each result is reported back under.
$inputs = array_map(
    static fn (string $question): MessageBag => new MessageBag(Message::ofUser($question)),
    [
        'capital-fr' => 'What is the capital of France?',
        'capital-de' => 'What is the capital of Germany?',
        'capital-it' => 'What is the capital of Italy?',
    ],
);

$handle = $platform->invoke('mistral-small-latest', $inputs, [
    'batch' => true,
    'max_tokens' => 50,
])->asJob();

echo 'Submitted batch '.$handle->getId().' with '.count($inputs).' requests.'.\PHP_EOL;

// Mistral takes up to 24 hours, so a real application stores the handle and picks it up in a worker.
$jobClient = Factory::createJobClient(env('MISTRAL_API_KEY'), http_client());
$progress = $jobClient->getProgress($handle);

while (!$progress['status']->isTerminal()) {
    echo 'Batch is "'.$progress['status']->getRaw().'": '.$progress['completed'].'/'.$progress['total'].' done, '.$progress['failed'].' failed.'.\PHP_EOL;

    sleep(10);

    $progress = $jobClient->getProgress($handle);
}

// The batch is terminal, so nothing is left to wait for - and a canceled or expired one still hands
// out what it got through.
foreach ($jobClient->getResult($handle)->getContent() as $item) {
    echo $item->getId().': '.($item->isSuccess() ? $item->getResult()->getContent() : 'failed - '.$item->getError()).\PHP_EOL;
}

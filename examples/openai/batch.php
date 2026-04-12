<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Batch\BatchInput;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::createBatch(env('OPENAI_API_KEY'), http_client());

$questions = [
    'req-1' => 'What is the capital of France? Answer in one word.',
    'req-2' => 'What is the capital of Germany? Answer in one word.',
    'req-3' => 'What is the capital of Spain? Answer in one word.',
];

$inputs = (static function () use ($questions): Generator {
    foreach ($questions as $id => $question) {
        yield new BatchInput($id, new MessageBag(Message::ofUser($question)));
    }
})();

$job = $platform->submitBatch('gpt-4o-mini', $inputs, ['max_tokens' => 50]);

output()->writeln(sprintf('<info>Batch submitted: %s (status: %s)</info>', $job->getId(), $job->getStatus()->value));
output()->writeln('Polling every 5s until complete...');

while (!$job->isTerminal()) {
    sleep(5);
    $job = $platform->getBatch($job->getId());
    output()->writeln(sprintf('  status: %s (%d/%d processed)', $job->getStatus()->value, $job->getProcessedCount(), $job->getTotalCount()));
}

if ($job->isFailed()) {
    output()->writeln('<error>Batch failed.</error>');
    exit(1);
}

output()->writeln('<info>Batch complete! Results:</info>');

foreach ($platform->fetchResults($job) as $result) {
    if ($result->isSuccess()) {
        output()->writeln(sprintf('  [%s] %s (tokens: %d in / %d out)', $result->getId(), $result->getContent(), $result->getInputTokens(), $result->getOutputTokens()));
    } else {
        output()->writeln(sprintf('  [%s] ERROR: %s', $result->getId(), $result->getError()));
    }
}

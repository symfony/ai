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
use Symfony\AI\Platform\Batch\BatchManager;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\ModelClient as BatchModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

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

// Submission goes through the regular invocation flow with the `batch` option.
$job = $platform->invoke('gpt-4o-mini', $inputs, ['batch' => true, 'max_output_tokens' => 50])->asBatchJob();

output()->writeln(sprintf('<info>Batch submitted: %s (status: %s)</info>', $job->getId(), $job->getStatus()->value));

// The job is a plain value object: it could be persisted here and resumed later.
$manager = new BatchManager(new BatchModelClient(http_client(), env('OPENAI_API_KEY')));

output()->writeln('Polling every 5s until complete...');

while (!$job->isTerminal()) {
    sleep(5);
    $job = $manager->refresh($job);
    output()->writeln(sprintf('  status: %s (%d/%d processed)', $job->getStatus()->value, $job->getProcessedCount(), $job->getTotalCount()));
}

if (!$job->isComplete()) {
    output()->writeln(sprintf('<error>Batch ended without completing (status: %s).</error>', $job->getStatus()->value));
    exit(1);
}

output()->writeln('<info>Batch complete! Results:</info>');

foreach ($manager->fetchResults($job) as $result) {
    if ($result->isSuccess()) {
        output()->writeln(sprintf('  [%s] %s (tokens: %d in / %d out)', $result->getId(), $result->getContent(), $result->getInputTokens(), $result->getOutputTokens()));
    } else {
        output()->writeln(sprintf('  [%s] ERROR: %s', $result->getId(), $result->getError()));
    }
}

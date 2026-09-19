<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Factory;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ScoreQuestion;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('TYPESAFE_API_KEY'), http_client());

$ticket = "Hi, I've been trying to connect my Stripe account for 3 days and it keeps failing. I'm losing sales. Please help ASAP.";

// Jev reads the state once and answers every question against it, so triaging a ticket takes a single call
$result = $platform->invoke('jev-latest', new Evaluation($ticket, [
    'department' => new ChoiceQuestion('Which team should handle this?', [
        'billing' => 'Payment or subscription issues',
        'technical' => 'Bugs or integration problems',
        'sales' => 'Pricing or account questions',
    ]),
    'frustration' => new ScoreQuestion('How frustrated does the customer appear?', [
        'Calm, just stating facts',
        'Frustrated but civil',
        'Very angry, strong language',
    ]),
    'is_urgent' => new NoulQuestion('Does the message convey urgency or time-sensitivity?'),
]));

$answers = $result->asObject();
assert($answers instanceof Answers);

$department = $answers->getChoice('department');
echo sprintf('Department: %s (confidence: %.2f)', $department->getChoice(), $department->getConfidence()).\PHP_EOL;
foreach ($department->getProbabilities() as $option => $probability) {
    echo sprintf('  %s: %.2f', $option, $probability).\PHP_EOL;
}

$frustration = $answers->getScore('frustration');
echo sprintf('Frustration: %.2f out of %d (confidence: %.2f)', $frustration->getScore(), count($frustration->getLegend()) - 1, $frustration->getConfidence()).\PHP_EOL;

$urgent = $answers->getNoul('is_urgent');
echo sprintf('Urgent: %s (probability: %.2f)', $urgent->isTrue() ? 'yes' : 'no', $urgent->getProbability()).\PHP_EOL;

// Confidence is what lets the code decide whether to act on an answer or to hand the ticket over to a human
if ($department->getConfidence() < 0.7) {
    echo 'The department is uncertain, routing the ticket to a human for review.'.\PHP_EOL;
}

print_token_usage($result->getMetadata()->get('token_usage'));

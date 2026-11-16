<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Bridge\Ollama\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform('http://localhost:11434', env('OLLAMA_API_KEY'), httpClient: http_client());

$ticket = "Hi, I've been trying to connect my Stripe account for 3 days and it keeps failing. I'm losing sales. Please help ASAP.";

$result = $platform->invoke('nimble', new Evaluation($ticket, [
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

$answers = $result->asObject(Answers::class);

$department = $answers->getChoice('department');
echo sprintf('Department: %s (confidence: %.2f)', $department->getChoice(), $department->getConfidence()).\PHP_EOL;

$frustration = $answers->getScore('frustration');
echo sprintf('Frustration: %.2f out of %d (confidence: %.2f)', $frustration->getScore(), count($frustration->getLegend()) - 1, $frustration->getConfidence()).\PHP_EOL;

$urgent = $answers->getNoul('is_urgent');
echo sprintf('Urgent: %s (probability: %.2f)', $urgent->isTrue() ? 'yes' : 'no', $urgent->getProbability()).\PHP_EOL;

print_token_usage($result->getMetadata()->get('token_usage'));

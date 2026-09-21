<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Jev\Factory;
use Symfony\AI\Platform\Bridge\Jev\Output\EvaluationResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('TYPESAFE_API_KEY'), http_client());

$result = $platform->invoke('jev-latest', 'Help! My payouts have been failing for 3 days.', [
    'questions' => [
        'is_urgent' => [
            'type' => 'noul',
            'instructions' => 'Does this convey urgency?',
        ],
        'department' => [
            'type' => 'choice',
            'instructions' => 'Which team should handle this?',
            'criteria' => [
                'billing' => 'Payments, invoicing, refunds',
                'technical' => 'Bugs, outages, integrations',
                'sales' => 'Pricing, upgrades, new accounts',
            ],
        ],
        'frustration' => [
            'type' => 'score',
            'instructions' => 'How frustrated is the customer?',
            'criteria' => ['Calm', 'Frustrated', 'Very angry'],
        ],
    ],
])->asObject();

assert($result instanceof EvaluationResult);

echo 'model: '.$result->getModel().\PHP_EOL;
echo 'urgent: '.$result->getNoul('is_urgent').\PHP_EOL;
echo 'department: '.$result->getChoice('department').\PHP_EOL;
echo 'frustration: '.$result->getScore('frustration').\PHP_EOL;

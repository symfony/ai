<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Cohere\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('COHERE_API_KEY'), http_client());

$result = $platform->invoke('rerank-v3.5', [
    'query' => 'What is artificial intelligence?',
    'texts' => [
        'Artificial intelligence is the simulation of human intelligence processes by machines.',
        'The weather today is sunny with a high of 75 degrees.',
        'Machine learning is a subset of AI that enables systems to learn from data.',
        'The best recipe for chocolate cake requires cocoa powder and butter.',
    ],
]);

foreach ($result->asReranking() as $entry) {
    echo sprintf("Index: %d, Score: %.4f\n", $entry->getIndex(), $entry->getScore());
}

print_token_usage($result->getMetadata()->get('token_usage'));

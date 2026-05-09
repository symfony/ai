<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenRouter\Factory;
use Symfony\AI\Platform\Bridge\OpenRouter\Rerank\RerankModelCatalog;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENROUTER_KEY'), http_client(), modelCatalog: new RerankModelCatalog());

$result = $platform->invoke('cohere/rerank-4-fast', [
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

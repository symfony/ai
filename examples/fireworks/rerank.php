<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Fireworks\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('FIREWORKS_API_KEY'), http_client());

$result = $platform->invoke('accounts/fireworks/models/qwen3-reranker-8b', [
    'query' => 'What is machine learning?',
    'documents' => [
        'Machine learning is a field of study that gives computers the ability to learn without being explicitly programmed.',
        'Cooking is the art of preparing food with heat.',
        'Deep learning is a subset of machine learning based on artificial neural networks.',
        'The weather today is sunny with a light breeze.',
    ],
]);

foreach ($result->asReranking() as $entry) {
    output()->writeln(sprintf('Document %d: relevance score %.4f', $entry->getIndex(), $entry->getScore()));
}

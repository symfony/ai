<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client());

$input = [
    'query' => 'select year where city = beijing',
    'table' => [
        'year' => ['1896', '1900', '1904', '2004', '2008', '2012'],
        'city' => ['athens', 'paris', 'st. louis', 'athens', 'beijing', 'london'],
    ],
];

$result = $platform->invoke('google/tapas-base-finetuned-wtq', $input, [
    'task' => Task::TABLE_QUESTION_ANSWERING,
]);

dump($result->asObject());

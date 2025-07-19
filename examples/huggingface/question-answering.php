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
use Symfony\AI\Platform\Model;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client());
$model = new Model('deepset/roberta-base-squad2');

$input = [
    'question' => 'What is the capital of France?',
    'context' => 'Paris is the capital and most populous city of France, with an estimated population of 2,175,601 residents as of 2018, in an area of more than 105 square kilometres.',
];

$response = $platform->request($model, $input, [
    'task' => Task::QUESTION_ANSWERING,
]);

dump($response->asObject());

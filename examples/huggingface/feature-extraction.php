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
$model = new Model('thenlper/gte-large');

$response = $platform->request($model, 'Today is a sunny day and I will get some ice cream.', [
    'task' => Task::FEATURE_EXTRACTION,
]);

echo 'Dimensions: '.$response->asVectors()[0]->getDimensions().\PHP_EOL;

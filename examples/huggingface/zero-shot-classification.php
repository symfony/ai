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
$model = new Model('facebook/bart-large-mnli');

$text = 'Hi, I recently bought a device from your company but it is not working as advertised and I would like to get reimbursed!';
$response = $platform->request($model, $text, [
    'task' => Task::ZERO_SHOT_CLASSIFICATION,
    'candidate_labels' => ['refund', 'legal', 'faq'],
]);

dump($response->asObject());

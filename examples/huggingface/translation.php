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
$model = new Model('facebook/mbart-large-50-many-to-many-mmt');

$response = $platform->request($model, 'Меня зовут Вольфганг и я живу в Берлине', [
    'task' => Task::TRANSLATION,
    'src_lang' => 'ru',
    'tgt_lang' => 'en',
]);

echo $response->asText().\PHP_EOL;

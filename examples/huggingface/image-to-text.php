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
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client());
$model = new Model('Salesforce/blip-image-captioning-base');

$image = Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg');
$response = $platform->request($model, $image, [
    'task' => Task::IMAGE_TO_TEXT,
]);

echo $response->asText().\PHP_EOL;

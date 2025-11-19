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

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client());

$image = Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg');
$result = $platform->invoke('google/vit-base-patch16-224', $image, [
    'task' => Task::IMAGE_CLASSIFICATION,
]);

dump($result->asObject());

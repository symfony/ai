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

// This model currently has no inference provider available on HuggingFace.
echo 'Skipped: No inference provider currently available for image-to-text task.'.\PHP_EOL;

return;

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client()); // @phpstan-ignore deadCode.unreachable

$image = Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg');
$result = $platform->invoke('Salesforce/blip-image-captioning-base', $image, [
    'task' => Task::IMAGE_TO_TEXT,
]);

echo $result->asText().\PHP_EOL;

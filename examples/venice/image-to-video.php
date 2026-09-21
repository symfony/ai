<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Venice\Factory;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Message\Content\Image;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('VENICE_API_KEY'), httpClient: http_client());

// See text-to-video.php on picking `duration` and `resolution` for a model. This one derives the
// aspect ratio from the source image and rejects `aspect_ratio` outright - its `model_spec`
// reports an empty `aspect_ratios` list.
$handle = $platform->invoke('pixverse-c1-image-to-video', Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg'), [
    'prompt' => 'Slowly zoom into the scene',
    'duration' => '3s',
    'resolution' => '360p',
])->asJob();

echo 'Started job '.$handle->getId().', waiting for it to finish...'.\PHP_EOL;

// Waiting is explicit. Replaying a cassette serves the status polls instantly, so skip the real waiting.
$jobClient = Factory::createJobClient(env('VENICE_API_KEY'), httpClient: http_client());
$result = (new JobRunner(clock()))->wait($jobClient, $handle);

$result->asFile(__DIR__.'/image-to-video.mp4');

echo 'Video saved to image-to-video.mp4'.\PHP_EOL;

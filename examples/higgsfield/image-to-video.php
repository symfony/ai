<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Higgsfield\Factory;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Message\Content\Image;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: env('HIGGSFIELD_API_KEY'),
    apiSecret: env('HIGGSFIELD_API_SECRET'),
    httpClient: http_client(),
);

// Higgsfield generates asynchronously, so the invocation returns a handle instead of a video.
$handle = $platform->invoke('kling-2.5-i2v', Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg'), [
    'prompt' => 'Slowly zoom into the scene',
    'duration' => 5,
])->asJob();

echo 'Started job '.$handle->getId().', waiting for it to finish...'.\PHP_EOL;

// The handle states how long to wait and how often to poll. Replaying a cassette serves the polls
// instantly, so skip the real waiting.
$jobClient = Factory::createJobClient(
    apiKey: env('HIGGSFIELD_API_KEY'),
    apiSecret: env('HIGGSFIELD_API_SECRET'),
    httpClient: http_client(),
);
$result = (new JobRunner(clock()))->wait($jobClient, $handle);

$result->asFile(__DIR__.'/image-to-video.mp4');

echo 'Video saved to image-to-video.mp4'.\PHP_EOL;

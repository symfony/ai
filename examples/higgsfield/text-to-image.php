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
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: env('HIGGSFIELD_API_KEY'),
    apiSecret: env('HIGGSFIELD_API_SECRET'),
    httpClient: http_client(),
);

// Higgsfield generates asynchronously, so the invocation returns a handle instead of an image.
$handle = $platform->invoke('soul-2', new Text('A cat on a kitchen table'), [
    'aspect_ratio' => '9:16',
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

$result->asFile(__DIR__.'/text-to-image.png');

echo 'Image saved to text-to-image.png'.\PHP_EOL;

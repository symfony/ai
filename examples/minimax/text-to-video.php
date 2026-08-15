<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\MiniMax\Factory;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('MINI_MAX_API_KEY'), http_client());

// Video generation is asynchronous: MiniMax accepts the request and answers with a task, so the
// invocation returns a handle instead of a video.
$handle = $platform->invoke('MiniMax-Hailuo-02', new Text('A cat playing the piano on a stage, cinematic lighting'), [
    'duration' => 6,
    'resolution' => '768P',
])->asJob();

echo 'Started job '.$handle->getId().', waiting for it to finish...'.\PHP_EOL;

// Waiting is explicit and the budget is yours to choose - video routinely runs for several minutes.
$runner = new JobRunner(pollInterval: 1.0, maxPolls: 600);
$result = $runner->wait($platform->getJobClient($handle), $handle);

$result->asFile(__DIR__.'/minimax-video.mp4');

echo 'Video saved to minimax-video.mp4'.\PHP_EOL;

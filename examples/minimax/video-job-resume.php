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
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

/*
 * A long-running job does not have to be waited for in the process that started it. Run this example
 * once to start a video job - it exits immediately, leaving only a handle on disk - and run it again
 * to pick the job up and download the result once it is done.
 *
 * The same handle is what you would put into a Messenger message or a database row to let a worker
 * finish the job.
 */

$platform = Factory::createPlatform(env('MINI_MAX_API_KEY'), http_client());
$storage = __DIR__.'/minimax-video-job.json';

if (!is_file($storage)) {
    $handle = $platform->invoke('MiniMax-Hailuo-02', new Text('A cat playing the piano on a stage, cinematic lighting'), [
        'duration' => 6,
        'resolution' => '768P',
    ])->asJob();

    file_put_contents($storage, json_encode($handle, \JSON_THROW_ON_ERROR));

    echo 'Started job '.$handle->getId().'. Run this example again to pick it up.'.\PHP_EOL;

    exit(0);
}

$handle = JobHandle::fromArray(json_decode((string) file_get_contents($storage), true, flags: \JSON_THROW_ON_ERROR));
$jobClient = $platform->getJobClient($handle);

$status = $jobClient->getStatus($handle);

echo 'Job '.$handle->getId().' is "'.$status->getRaw().'".'.\PHP_EOL;

if (!$status->is(JobStateCase::SUCCEEDED)) {
    echo $status->isTerminal()
        ? 'It will not produce a result'.(null !== $status->getError() ? ': '.$status->getError() : '.').\PHP_EOL
        : 'Still running - run this example again in a moment.'.\PHP_EOL;

    exit(0);
}

// The job is done, so the runner returns without waiting - and hands back the same kind of result
// a synchronous invocation would have.
(new JobRunner())->wait($jobClient, $handle)->asFile(__DIR__.'/minimax-video.mp4');
unlink($storage);

echo 'Video saved to minimax-video.mp4'.\PHP_EOL;

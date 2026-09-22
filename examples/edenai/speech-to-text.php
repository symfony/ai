<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\EdenAi\Factory;
use Symfony\AI\Platform\Exception\JobTimeoutException;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Result\JobResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

$result = $platform->invoke('audio/speech_to_text_async/openai', 'https://raw.githubusercontent.com/symfony/ai/main/fixtures/audio.mp3', [
    'language' => 'en',
]);

// Transcription runs on Eden AI's asynchronous endpoint. A provider that is done by the time it
// answers puts the text right into the response; the others hand out a job instead.
if ($result->getResult() instanceof JobResult) {
    $handle = $result->asJob();

    echo 'Started job '.$handle->getId().', waiting for it to finish...'.\PHP_EOL;

    $jobClient = Factory::createJobClient(env('EDENAI_API_KEY'), http_client());

    try {
        // The handle allows minutes, which an example should not spend blocking - so it waits for
        // a minute and gives up. Nothing is lost by that: the handle holds no connection, so real
        // code stores it and resumes the job from a worker, possibly in another process.
        $result = (new JobRunner())->wait($jobClient, $handle, maxDuration: 60);
    } catch (JobTimeoutException) {
        echo 'Still running. Resume it later with:'.\PHP_EOL;
        echo '    (new JobRunner())->wait($jobClient, JobHandle::fromString(\''.$handle->toString().'\'));'.\PHP_EOL;

        return;
    }
}

echo $result->asText().\PHP_EOL;

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
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Result\JobResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

// The local audio file is transparently uploaded through Eden AI's /v3/upload endpoint
$result = $platform->invoke('audio/speech_to_text_async/deepgram', Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'), [
    'language' => 'en',
]);

// As in speech-to-text.php, the answer is either the transcription or a job still running.
if ($result->getResult() instanceof JobResult) {
    $handle = $result->asJob();
    $jobClient = Factory::createJobClient(env('EDENAI_API_KEY'), http_client());

    try {
        $result = (new JobRunner())->wait($jobClient, $handle, maxDuration: 60);
    } catch (JobTimeoutException) {
        echo 'Job '.$handle->getId().' is still running, see speech-to-text.php.'.\PHP_EOL;

        return;
    }
}

echo $result->asText().\PHP_EOL;

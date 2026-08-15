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

// The async endpoint enqueues a task, so the invocation hands back a job handle instead of audio.
$handle = $platform->invoke('speech-2.6-hd', new Text('The real danger is not that computers start thinking like people, but that people start thinking like computers.'), [
    'async' => true,
    'voice_setting' => [
        'voice_id' => 'English_expressive_narrator',
        'speed' => 1,
        'vol' => 1,
        'pitch' => 0,
    ],
    'audio_setting' => [
        'sample_rate' => 32000,
        'bitrate' => 128000,
        'format' => 'mp3',
        'channel' => 1,
    ],
])->asJob();

$result = (new JobRunner())->wait($platform->getJobClient($handle), $handle);

// MiniMax delivers the asynchronous result as a tar bundling the mp3 with a `.titles` and an
// `.extra` file; the bridge unpacks the audio, so this is the same mp3 the synchronous endpoint
// would have returned.
$result->asFile(__DIR__.'/minimax-speech.mp3');

echo 'Speech saved to minimax-speech.mp3'.\PHP_EOL;

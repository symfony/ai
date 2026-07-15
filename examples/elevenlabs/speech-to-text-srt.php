<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ElevenLabs\Factory;
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(apiKey: env('ELEVEN_LABS_API_KEY'), httpClient: http_client());

$result = $platform->invoke(
    model: 'scribe_v2',
    input: Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'),
    options: [
        'language_code' => 'en',
        'tag_audio_events' => false,
        'num_speakers' => 1,
        'diarize' => true,
        'timestamps_granularity' => 'word',
        'additional_formats' => [
            ['format' => 'srt', 'include_timestamps' => true],
        ],
    ],
);

echo $result->asObject()->asSubRipText().\PHP_EOL;

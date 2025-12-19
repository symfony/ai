<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser('TTS the following conversation between Joe and Jane:
Joe: Hows it going today Jane?
Jane: Not too bad, how about you?'),
);
$result = $platform->invoke('gemini-2.5-flash-preview-tts', $messages, [
    'responseModalities' => ['AUDIO'],
    'speechConfig' => [
        'multiSpeakerVoiceConfig' => [
            'speakerVoiceConfigs' => [
                [
                    'speaker' => 'Joe',
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => 'Kore',
                        ],
                    ],
                ],
                [
                    'speaker' => 'Jane',
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => 'Puck',
                        ],
                    ],
                ],
            ],
        ],
    ],
]);

// Example call
// php examples/gemini/multi-speaker-voice.php > out.pcm
// ffmpeg -f s16le -ar 24000 -ac 1 -i out.pcm out.wav

echo $result->asBinary().\PHP_EOL;

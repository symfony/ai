<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    env('ELEVEN_LABS_URL'),
    env('ELEVEN_LABS_API_KEY'),
    __DIR__.'/tmp',
    http_client()
);
$model = new ElevenLabs(ElevenLabs::SPEECH_TO_TEXT, options: [
    'model' => 'scribe_v1',
]);
$file = Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3');

$result = $platform->invoke($model, $file);

echo $result->asText().\PHP_EOL;

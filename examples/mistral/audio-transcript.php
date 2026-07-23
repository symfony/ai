<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('MISTRAL_API_KEY'), http_client());

// "voxtral-mini-latest" is a chat model by default; wrap it in the SpeechToText model to
// opt into the dedicated /v1/audio/transcriptions endpoint (transcription only, no chat).
$result = $platform->invoke(new SpeechToText('voxtral-mini-latest'), Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'), [
    'language' => 'en',
]);

echo $result->asText().\PHP_EOL;

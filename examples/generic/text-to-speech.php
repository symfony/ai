<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\Bridge\Generic\ModelCatalog;
use Symfony\AI\Platform\Bridge\Generic\TextToSpeechModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

// Any server implementing OpenAI's POST /v1/audio/speech, e.g. Kokoro-FastAPI, LocalAI, Speaches or VoiceStudio
$platform = Factory::createPlatform(
    env('GENERIC_TTS_HOST_URL'),
    httpClient: http_client(),
    modelCatalog: new ModelCatalog([
        env('GENERIC_TTS_MODEL') => [
            'class' => TextToSpeechModel::class,
            'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH],
        ],
    ]),
    supportsCompletions: false,
    supportsEmbeddings: false,
    supportsTextToSpeech: true,
);

$result = $platform->invoke(env('GENERIC_TTS_MODEL'), new Text('Hello world'), [
    'voice' => env('GENERIC_TTS_VOICE'),
]);

echo $result->asBinary();

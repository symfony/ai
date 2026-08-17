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
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

// The local audio file is transparently uploaded through Eden AI's /v3/upload endpoint
$result = $platform->invoke('audio/speech_to_text_async/deepgram', Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'), [
    'language' => 'en',
]);

echo $result->asText().\PHP_EOL;

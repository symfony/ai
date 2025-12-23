<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsClient;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsResultConverter;
use Symfony\AI\Platform\Bridge\ElevenLabs\ModelCatalog;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Platform;

require_once dirname(__DIR__).'/bootstrap.php';

$elevenLabsClient = new ElevenLabsClient(
    env('ELEVEN_LABS_API_KEY'),
    httpClient: http_client(),
);

$platform = new Platform([$elevenLabsClient], [new ElevenLabsResultConverter(http_client())], new ModelCatalog());

$result = $platform->invoke('scribe_v1', Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'));

echo $result->asText().\PHP_EOL;

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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Platform;

require_once dirname(__DIR__).'/bootstrap.php';

$elevenLabsClient = new ElevenLabsClient(
    env('ELEVEN_LABS_API_KEY'),
    httpClient: http_client(),
);

$platform = new Platform([$elevenLabsClient], [new ElevenLabsResultConverter(http_client())], new ModelCatalog());

$result = $platform->invoke('eleven_multilingual_v2', new Text('The first move is what sets everything in motion.'), [
    'voice' => 'Dslrhjl3ZpzrctukrQSN', // Brad (https://elevenlabs.io/app/voice-library?voiceId=Dslrhjl3ZpzrctukrQSN)
    'stream' => true,
]);

$content = '';

foreach ($result->asStream() as $chunk) {
    echo $chunk;
}

echo \PHP_EOL;

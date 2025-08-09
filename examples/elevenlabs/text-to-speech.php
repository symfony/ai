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
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    env('ELEVEN_LABS_URL'),
    env('ELEVEN_LABS_API_KEY'),
    __DIR__.'/tmp',
    http_client(),
);
$model = new ElevenLabs(options: [
    'model' => 'Dslrhjl3ZpzrctukrQSN', // Brad (https://elevenlabs.io/app/voice-library?voiceId=Dslrhjl3ZpzrctukrQSN)
]);

$result = $platform->invoke($model, new Text('Hello world'));

echo $result->asAudio().\PHP_EOL;

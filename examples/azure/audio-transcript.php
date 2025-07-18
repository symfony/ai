<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Azure\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAI\Whisper;
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    env('AZURE_OPENAI_BASEURL'),
    env('AZURE_OPENAI_WHISPER_DEPLOYMENT'),
    env('AZURE_OPENAI_WHISPER_API_VERSION'),
    env('AZURE_OPENAI_KEY'),
    http_client(),
);
$model = new Whisper();
$file = Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3');

$response = $platform->request($model, $file);

echo $response->asText().\PHP_EOL;

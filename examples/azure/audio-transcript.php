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
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!isset($_SERVER['AZURE_OPENAI_BASEURL'], $_SERVER['AZURE_OPENAI_WHISPER_DEPLOYMENT'], $_SERVER['AZURE_OPENAI_WHISPER_API_VERSION'], $_SERVER['AZURE_OPENAI_KEY'])) {
    echo 'Please set the AZURE_OPENAI_BASEURL, AZURE_OPENAI_WHISPER_DEPLOYMENT, AZURE_OPENAI_WHISPER_API_VERSION, and AZURE_OPENAI_KEY environment variables.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create(
    $_SERVER['AZURE_OPENAI_BASEURL'],
    $_SERVER['AZURE_OPENAI_WHISPER_DEPLOYMENT'],
    $_SERVER['AZURE_OPENAI_WHISPER_API_VERSION'],
    $_SERVER['AZURE_OPENAI_KEY'],
);
$model = new Whisper();
$file = Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3');

$result = $platform->invoke($model, $file);

echo $result->asText().\PHP_EOL;

<?php

use Symfony\AI\Platform\Bridge\Azure\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAI\Whisper;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__, 2).'/.env');

if (empty($_ENV['AZURE_OPENAI_BASEURL']) || empty($_ENV['AZURE_OPENAI_WHISPER_DEPLOYMENT']) || empty($_ENV['AZURE_OPENAI_WHISPER_API_VERSION']) || empty($_ENV['AZURE_OPENAI_KEY'])
) {
    echo 'Please set the AZURE_OPENAI_BASEURL, AZURE_OPENAI_WHISPER_DEPLOYMENT, AZURE_OPENAI_WHISPER_API_VERSION, and AZURE_OPENAI_KEY environment variables.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create(
    $_ENV['AZURE_OPENAI_BASEURL'],
    $_ENV['AZURE_OPENAI_WHISPER_DEPLOYMENT'],
    $_ENV['AZURE_OPENAI_WHISPER_API_VERSION'],
    $_ENV['AZURE_OPENAI_KEY'],
);
$model = new Whisper();
$file = Audio::fromFile(dirname(__DIR__, 2).'/tests/Fixture/audio.mp3');

$response = $platform->request($model, $file);

echo $response->getContent().\PHP_EOL;

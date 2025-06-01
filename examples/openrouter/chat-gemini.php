<?php

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__, 2).'/.env');

if (empty($_ENV['OPENROUTER_KEY'])) {
    echo 'Please set the OPENROUTER_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['OPENROUTER_KEY']);
// In case free is running into 429 rate limit errors, you can use the paid model:
// $model = new Model('google/gemini-2.0-flash-lite-001');
$model = new Model('google/gemini-2.0-flash-exp:free');

$agent = new Agent($platform, $model);
$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
);
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;

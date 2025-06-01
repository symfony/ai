<?php

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__, 2).'/.env');

if (empty($_ENV['MISTRAL_API_KEY'])) {
    echo 'Please set the REPLICATE_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['MISTRAL_API_KEY']);
$model = new Mistral();
$agent = new Agent($platform, $model);

$messages = new MessageBag(Message::ofUser('What is the eighth prime number?'));
$response = $agent->call($messages, [
    'stream' => true,
]);

foreach ($response->getContent() as $word) {
    echo $word;
}
echo \PHP_EOL;

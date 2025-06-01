<?php

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\Azure\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__, 2).'/.env');

if (empty($_ENV['AZURE_OPENAI_BASEURL']) || empty($_ENV['AZURE_OPENAI_GPT_DEPLOYMENT']) || empty($_ENV['AZURE_OPENAI_GPT_API_VERSION']) || empty($_ENV['AZURE_OPENAI_KEY'])
) {
    echo 'Please set the AZURE_OPENAI_BASEURL, AZURE_OPENAI_GPT_DEPLOYMENT, AZURE_OPENAI_GPT_API_VERSION, and AZURE_OPENAI_KEY environment variables.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create(
    $_ENV['AZURE_OPENAI_BASEURL'],
    $_ENV['AZURE_OPENAI_GPT_DEPLOYMENT'],
    $_ENV['AZURE_OPENAI_GPT_API_VERSION'],
    $_ENV['AZURE_OPENAI_KEY'],
);
$model = new GPT(GPT::GPT_4O_MINI);

$agent = new Agent($platform, $model);
$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;

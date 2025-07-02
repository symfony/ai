<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\Azure\Meta\PlatformFactory;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!$_ENV['AZURE_LLAMA_BASEURL'] || !$_ENV['AZURE_LLAMA_KEY']) {
    echo 'Please set the AZURE_LLAMA_BASEURL and AZURE_LLAMA_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['AZURE_LLAMA_BASEURL'], $_ENV['AZURE_LLAMA_KEY']);
$model = new Llama(Llama::V3_3_70B_INSTRUCT);

$agent = new Agent($platform, $model);
$messages = new MessageBag(Message::ofUser('I am going to Paris, what should I see?'));
$response = $agent->call($messages, [
    'max_tokens' => 2048,
    'temperature' => 0.8,
    'top_p' => 0.1,
    'presence_penalty' => 0,
    'frequency_penalty' => 0,
]);

echo $response->getContent().\PHP_EOL;

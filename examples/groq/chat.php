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
use Symfony\AI\Platform\Bridge\Groq\Llama;
use Symfony\AI\Platform\Bridge\Groq\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

$platform = PlatformFactory::create($_SERVER['GROQ_API_KEY']);
$model = new Llama(Llama::LLAMA3_70B, [
    'temperature' => 0.5, // default options for the model
]);

$agent = new Agent($platform, $model);
$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$response = $agent->call($messages, [
    'max_tokens' => 500, // specific options just for this call
]);

echo $response->getContent().PHP_EOL;

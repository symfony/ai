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
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAI\Responses;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!isset($_SERVER['OPENAI_API_KEY'])) {
    echo 'Please set the OPENAI_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_SERVER['OPENAI_API_KEY']);
$model = new Responses(Responses::GPT_4O_MINI);

$agent = new Agent($platform, $model);
$messages = new MessageBag(Message::ofUser('What was a positive news story from today?'));
$response = $agent->call($messages, [
    'stream' => true, // enable streaming of response text
    'tools' => [
        [
            'type' => 'web_search_preview',
        ],
    ],
]);

foreach ($response->getContent() as $word) {
    echo $word;
}

echo \PHP_EOL;

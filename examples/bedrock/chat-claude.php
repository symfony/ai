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
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!$_ENV['AWS_ACCESS_KEY_ID'] || !$_ENV['AWS_SECRET_ACCESS_KEY'] || !$_ENV['AWS_DEFAULT_REGION']
) {
    echo 'Please set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY and AWS_DEFAULT_REGION environment variables.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create();
$model = new Claude();

$agent = new Agent($platform, $model);
$messages = new MessageBag(
    Message::forSystem('You answer questions in short and concise manner.'),
    Message::ofUser('What is the Symfony framework?'),
);
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;

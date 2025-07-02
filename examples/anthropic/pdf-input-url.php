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
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!$_ENV['ANTHROPIC_API_KEY']) {
    echo 'Please set the ANTHROPIC_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['ANTHROPIC_API_KEY']);
$model = new Claude(Claude::SONNET_37);

$agent = new Agent($platform, $model);
$messages = new MessageBag(
    Message::ofUser(
        new DocumentUrl('https://upload.wikimedia.org/wikipedia/commons/2/20/Re_example.pdf'),
        'What is this document about?',
    ),
);
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;

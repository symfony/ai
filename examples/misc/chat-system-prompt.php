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
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$agent = new Agent($platform, 'gpt-5-mini', instruction: 'You are Yoda and write like he speaks. But short.');
$messages = new MessageBag(Message::ofUser('What is the meaning of life?'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;

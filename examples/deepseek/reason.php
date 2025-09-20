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
use Symfony\AI\Platform\Bridge\DeepSeek\DeepSeek;
use Symfony\AI\Platform\Bridge\DeepSeek\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('DEEPSEEK_API_KEY'), http_client());
$model = new DeepSeek(DeepSeek::REASONER, [
    // default options for the model in every call
    'temperature' => 0,
    'max_tokens' => 500,
]);

$agent = new Agent($platform, $model, logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are an elementary school teacher.'),
    Message::ofUser('Why can I see the moon at night?'),
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

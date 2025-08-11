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
use Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    env('AZURE_OPENAI_BASEURL'),
    env('AZURE_OPENAI_GPT_DEPLOYMENT'),
    env('AZURE_OPENAI_GPT_API_VERSION'),
    env('AZURE_OPENAI_KEY'),
    http_client(),
);
$model = Gpt::create(Gpt::GPT_4O_MINI);

$agent = new Agent($platform, $model, logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

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
use Symfony\AI\Platform\Bridge\Meilisearch\MessageBag;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new Gpt(Gpt::GPT_4O_MINI, [
    'temperature' => 0.5, // default options for the model
]);

$agent = new Agent($platform, $model, logger: logger());

$messages = new MessageBag(
    http_client(),
    env('MEILISEARCH_HOST'),
    env('MEILISEARCH_API_KEY'),
    'meilisearch_agent'
);

// Initialize the bag
$messages->initialize();

$messages->add(Message::forSystem('You are a pirate and you write funny.'));
$messages->add(Message::ofUser('What is the Symfony framework?'));

$result = $agent->call($messages, [
    'max_tokens' => 500, // specific options just for this call
]);

echo $result->getContent().\PHP_EOL;

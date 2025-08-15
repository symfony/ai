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
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\TokenOutputProcessor;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new Gpt(Gpt::GPT_4O_MINI, [
    'temperature' => 0.5, // default options for the model
]);

$agent = new Agent($platform, $model, outputProcessors: [new TokenOutputProcessor()], logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $agent->call($messages, [
    'max_tokens' => 500, // specific options just for this call
]);

$metadata = $result->getMetadata();

echo 'Utilized Tokens: '.$metadata['total_tokens'].\PHP_EOL;
echo '-- Prompt Tokens: '.$metadata['prompt_tokens'].\PHP_EOL;
echo '-- Completion Tokens: '.$metadata['completion_tokens'].\PHP_EOL;
echo 'Remaining Tokens: '.$metadata['remaining_tokens'].\PHP_EOL;

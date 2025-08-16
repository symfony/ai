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
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = PlatformFactory::create(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), adc_aware_http_client());
$model = new Model(Model::GEMINI_2_0_FLASH_LITE);

$agent = new Agent($platform, $model, outputProcessors: [new Symfony\AI\Platform\Bridge\VertexAi\TokenOutputProcessor()], logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are an expert assistant in animal study.'),
    Message::ofUser('What does a cat usually eat?'),
);
$result = $agent->call($messages);

$metadata = $result->getMetadata();

echo 'Prompt Tokens: '.$metadata['prompt_tokens'].\PHP_EOL;
echo 'Completion Tokens: '.$metadata['completion_tokens'].\PHP_EOL;
echo 'Thinking Tokens: '.$metadata['thinking_tokens'].\PHP_EOL;
echo 'Utilized Tokens: '.$metadata['total_tokens'].\PHP_EOL;

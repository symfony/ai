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
use Symfony\AI\Agent\StructuredOutput\AgentProcessor;
use Symfony\AI\Fixtures\StructuredOutput\PolymorphicType\ListOfPolymorphicTypesDto;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new Gpt(Gpt::GPT_4O_MINI);

$processor = new AgentProcessor();
$agent = new Agent($platform, $model, [$processor], [$processor], logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are a persona data collector! Return all the data you can gather from the user input.'),
    Message::ofUser('Hi! My name is John Doe, I am 30 years old and I live in Paris.'),
);
$result = $agent->call($messages, ['output_structure' => ListOfPolymorphicTypesDto::class]);

dump($result->getContent());

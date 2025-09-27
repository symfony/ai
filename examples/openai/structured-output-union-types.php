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
use Symfony\AI\Fixtures\StructuredOutput\UnionType\UnionTypeDto;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$processor = new AgentProcessor();
$agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor], logger: logger());
$messages = new MessageBag(
    Message::forSystem(<<<PROMPT
        You are a time assistant! You can provide time either as a unix timestamp or as a human readable time format.
        If you don't know the time, return null.
    PROMPT),
    Message::ofUser('What is the current time?'),
);
$result = $agent->call($messages, ['output_structure' => UnionTypeDto::class]);

dump($result->getContent());

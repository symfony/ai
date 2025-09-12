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
use Symfony\AI\Agent\StructuredOutput\AgentProcessor as StructuredOutputProcessor;
use Symfony\AI\Agent\Toolbox\AgentProcessor as ToolProcessor;
use Symfony\AI\Agent\Toolbox\Tool\Clock;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\DeepSeek\DeepSeek;
use Symfony\AI\Platform\Bridge\DeepSeek\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\Clock as SymfonyClock;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('DEEPSEEK_API_KEY'), http_client());
$model = new DeepSeek(DeepSeek::CHAT);

$clock = new Clock(new SymfonyClock());
$toolbox = new Toolbox([$clock], logger: logger());
$toolProcessor = new ToolProcessor($toolbox);
$structuredOutputProcessor = new StructuredOutputProcessor();
$agent = new Agent($platform, $model, [$toolProcessor, $structuredOutputProcessor], [$toolProcessor, $structuredOutputProcessor]);

$messages = new MessageBag(
    // for DeepSeek it is *mandatory* to mention JSON anywhere in the prompt when using structured output
    Message::forSystem('Respond in JSON as instructed in the response format.'),
    Message::ofUser('What date and time is it?')
);
$result = $agent->call($messages, ['response_format' => [
    'type' => 'json_object',
    'json_object' => [
        'name' => 'clock',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => 'The current date in the format YYYY-MM-DD.'],
                'time' => ['type' => 'string', 'description' => 'The current time in the format HH:MM:SS.'],
            ],
            'required' => ['date', 'time'],
            'additionalProperties' => false,
        ],
    ],
]]);

dump($result->getContent());

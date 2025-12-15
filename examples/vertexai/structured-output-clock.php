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
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\Clock\Clock as SymfonyClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once __DIR__.'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());

$platform = PlatformFactory::create(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), httpClient: adc_aware_http_client(), eventDispatcher: $dispatcher);

$clock = new Clock(new SymfonyClock());
$toolbox = new Toolbox([$clock], logger: logger());
$toolProcessor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gemini-2.5-pro', [$toolProcessor], [$toolProcessor]);

$messages = new MessageBag(Message::ofUser('What date and time is it?'));
$result = $agent->call($messages, ['response_format' => [
    'type' => 'json_schema',
    'json_schema' => [
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

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
use Symfony\AI\Platform\Bridge\Google\Gemini;
use Symfony\AI\Platform\Bridge\Google\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\Clock as SymfonyClock;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!isset($_SERVER['GEMINI_API_KEY'])) {
    echo 'Please set the GEMINI_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_SERVER['GEMINI_API_KEY']);
$model = new Gemini(Gemini::GEMINI_1_5_FLASH);

$clock = new Clock(new SymfonyClock());
$toolbox = new Toolbox([$clock]);
$toolProcessor = new ToolProcessor($toolbox);
$structuredOutputProcessor = new StructuredOutputProcessor();
$agent = new Agent($platform, $model, [$toolProcessor, $structuredOutputProcessor], [$toolProcessor, $structuredOutputProcessor]);

$messages = new MessageBag(Message::ofUser('What date and time is it?'));
$response = $agent->call($messages, ['response_format' => [
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

dump($response->getContent());

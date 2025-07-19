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
use Symfony\AI\Agent\StructuredOutput\Responses\ResponsesAgentProcessor;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAI\Responses;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__) . '/.env');

if (!isset($_SERVER['OPENAI_API_KEY'])) {
    echo 'Please set the OPENAI_API_KEY environment variable.' . \PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_SERVER['OPENAI_API_KEY']);
$model = new Responses(Responses::GPT_4O_MINI);

$structuredOutputProcessor = new ResponsesAgentProcessor();

$agent = new Agent($platform, $model, [$structuredOutputProcessor], [$structuredOutputProcessor]);
$messages = new MessageBag(
    Message::ofUser('What date and time is it?'),
);
$response = $agent->call($messages, [
    'text' => [
        'format' => [
            'type' => 'json_schema',
            'name' => 'clock',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'date' => [
                        'type' => 'string',
                        'description' => 'The current date in the format YYYY-MM-DD.',
                    ],
                    'time' => [
                        'type' => 'string',
                        'description' => 'The current time in the format HH:MM:SS.',
                    ],
                ],
                'required' => ['date', 'time'],
                'additionalProperties' => false,
            ],
        ],
    ],
]);

dump($response->getContent());

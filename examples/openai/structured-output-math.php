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
use Symfony\AI\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!$_ENV['OPENAI_API_KEY']) {
    echo 'Please set the OPENAI_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
$model = new GPT(GPT::GPT_4O_MINI);

$processor = new AgentProcessor();
$agent = new Agent($platform, $model, [$processor], [$processor]);
$messages = new MessageBag(
    Message::forSystem('You are a helpful math tutor. Guide the user through the solution step by step.'),
    Message::ofUser('how can I solve 8x + 7 = -23'),
);
$response = $agent->call($messages, ['output_structure' => MathReasoning::class]);

dump($response->getContent());

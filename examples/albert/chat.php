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
use Symfony\AI\Platform\Bridge\Albert\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/../vendor/autoload.php';

if (!isset($_SERVER['ALBERT_API_KEY'], $_SERVER['ALBERT_API_URL'])) {
    echo 'Please set the ALBERT_API_KEY and ALBERT_API_URL environment variable (e.g., https://your-albert-instance.com/v1).'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_SERVER['ALBERT_API_KEY'], $_SERVER['ALBERT_API_URL']);

$model = new GPT('gpt-4o');
$agent = new Agent($platform, $model);

$documentContext = <<<'CONTEXT'
    Document: AI Strategy of France

    France has launched a comprehensive national AI strategy with the following key objectives:
    1. Strengthening the AI ecosystem and attracting talent
    2. Developing sovereign AI capabilities
    3. Ensuring ethical and responsible AI development
    4. Supporting AI adoption in public services
    5. Investing €1.5 billion in AI research and development

    The Albert project is part of this strategy, providing a sovereign AI solution for French public administration.
    CONTEXT;

$messages = new MessageBag(
    Message::forSystem(
        'You are an AI assistant with access to documents about French AI initiatives. '.
        'Use the provided context to answer questions accurately.'
    ),
    Message::ofUser($documentContext),
    Message::ofUser('What are the main objectives of France\'s AI strategy?'),
);

$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

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
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

if (!isset($_SERVER['RUN_EXPENSIVE_EXAMPLES']) || false === filter_var($_SERVER['RUN_EXPENSIVE_EXAMPLES'], \FILTER_VALIDATE_BOOLEAN)) {
    echo 'This example is marked as expensive and will not run unless RUN_EXPENSIVE_EXAMPLES is set to true.'.\PHP_EOL;
    exit(134);
}

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$prompt = <<<PROMPT
    I want to build a Symfony app in PHP 8.2 that takes user questions and looks them
    up in a database where they are mapped to answers. If there is close match, it
    retrieves the matched answer. If there isn't, it asks the user to provide an answer
    and stores the question/answer pair in the database. Make a plan for the directory
    structure you'll need, then return each file in full. Only supply your reasoning
    at the beginning and end, not throughout the code.
    PROMPT;

$agent = new Agent($platform, 'o1-preview', logger: logger());
$result = $agent->call(new MessageBag(Message::ofUser($prompt)));

echo $result->getContent().\PHP_EOL;

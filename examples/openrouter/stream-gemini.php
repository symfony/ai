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
use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENROUTER_KEY'), http_client());
// In case free is running into 429 rate limit errors, you can use the paid model:
// $model = new Model('google/gemini-2.0-flash-lite-001');
$model = new Model('google/gemini-2.0-flash-exp:free');

$agent = new Agent($platform, $model, logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant and explain your answer lengthy.'),
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
);
$result = $agent->call($messages, [
    'stream' => true,
]);

foreach ($result->getContent() as $word) {
    echo $word;
}
echo \PHP_EOL;

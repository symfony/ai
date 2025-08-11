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
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('ANTHROPIC_API_KEY'), httpClient: http_client());
$model = Claude::create();

$agent = new Agent($platform, $model, logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are a thoughtful philosopher.'),
    Message::ofUser('What is the purpose of an ant?'),
);
$result = $agent->call($messages, [
    'stream' => true, // enable streaming of response text
]);

foreach ($result->getContent() as $word) {
    echo $word;
}
echo \PHP_EOL;

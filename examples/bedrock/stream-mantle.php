<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\BedrockMantle\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: env('AWS_BEARER_TOKEN_BEDROCK'),
    region: env('AWS_DEFAULT_REGION'),
    httpClient: http_client(),
);

$messages = new MessageBag(
    Message::forSystem('You are a thoughtful philosopher.'),
    Message::ofUser('What is the purpose of an ant?'),
);
$result = $platform->invoke('openai.gpt-oss-120b', $messages, ['stream' => true]);

foreach ($result->asStream() as $word) {
    echo $word;
}
echo \PHP_EOL;

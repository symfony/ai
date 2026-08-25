<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// A Bedrock API key takes precedence when configured; otherwise the standard AWS credential chain
// is used for SigV4 authentication.
$platform = Factory::createPlatform(
    apiKey: optional_env('AWS_BEARER_TOKEN_BEDROCK'),
    region: env('AWS_DEFAULT_REGION'),
    httpClient: http_client(),
);

$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $platform->invoke('openai.gpt-oss-120b', $messages);

echo $result->asText().\PHP_EOL;

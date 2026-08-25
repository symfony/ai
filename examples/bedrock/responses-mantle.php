<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Responses\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// The Bedrock Mantle Responses endpoint is the OpenAI-compatible Responses API that AWS recommends
// for new applications. A Bedrock API key takes precedence when configured; otherwise requests are
// authenticated with AWS SigV4.
$platform = Factory::createPlatform(
    apiKey: optional_env('AWS_BEARER_TOKEN_BEDROCK'),
    region: env('AWS_DEFAULT_REGION'),
    httpClient: http_client(),
);

$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $platform->invoke('google.gemma-4-31b', $messages);

echo $result->asText().\PHP_EOL;

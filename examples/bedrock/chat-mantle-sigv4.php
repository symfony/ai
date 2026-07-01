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

if (!isset($_SERVER['AWS_DEFAULT_REGION'])) {
    echo 'Please set the AWS_DEFAULT_REGION environment variable.'.\PHP_EOL;
    exit(1);
}

// Without an API key, the Mantle bridge authenticates with AWS SigV4 using the standard credential
// chain (environment variables, shared config/credentials files, instance metadata, etc.).
$platform = Factory::createPlatform(
    region: env('AWS_DEFAULT_REGION'),
    httpClient: http_client(),
);

$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $platform->invoke('openai.gpt-oss-120b', $messages);

echo $result->asText().\PHP_EOL;

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: optional_env('AWS_BEARER_TOKEN_BEDROCK'),
    region: env('AWS_DEFAULT_REGION'),
    httpClient: http_client(),
);

$messages = new MessageBag(Message::ofUser('Write one short sentence about cloud computing.'));
$result = $platform->invoke('anthropic.claude-haiku-4-5', $messages, ['stream' => true]);

foreach ($result->asTextStream() as $delta) {
    echo $delta;
}
echo \PHP_EOL;

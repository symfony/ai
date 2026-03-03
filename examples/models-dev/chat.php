<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ModelsDev\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    provider: 'anthropic',
    apiKey: env('ANTHROPIC_API_KEY'),
    httpClient: http_client(),
);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('What is the Symfony framework?'),
);

$result = $platform->invoke('claude-haiku-4-5', $messages);

echo $result->asText().\PHP_EOL;

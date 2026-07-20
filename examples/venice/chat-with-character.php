<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Venice\Factory;
use Symfony\AI\Platform\Bridge\Venice\VeniceParameters;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('VENICE_API_KEY'), httpClient: http_client());

$messages = new MessageBag(
    Message::ofUser('What is the meaning of life?'),
);

$result = $platform->invoke('venice-uncensored', $messages, [
    'venice_parameters' => new VeniceParameters(characterSlug: 'alan-watts'),
]);

echo $result->asText().\PHP_EOL;

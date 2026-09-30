<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Perplexity\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = Factory::createPlatform(env('PERPLEXITY_API_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('What is 2 + 2?'));
// A model without the web_search tool answers without searching the web
$result = $platform->invoke('perplexity/sonar', $messages);

echo $result->asText().\PHP_EOL;

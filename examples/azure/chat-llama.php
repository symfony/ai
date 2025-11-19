<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Azure\Meta\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('AZURE_LLAMA_BASEURL'), env('AZURE_LLAMA_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('I am going to Paris, what should I see?'));
$result = $platform->invoke('llama-3.3-70B-Instruct', $messages, [
    'max_tokens' => 2048,
    'temperature' => 0.8,
    'top_p' => 0.1,
    'presence_penalty' => 0,
    'frequency_penalty' => 0,
]);

echo $result->asText().\PHP_EOL;

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser('What is the latest stable Symfony version? Use web search to be sure.'),
);

// `web_search` is OpenAI's built-in, server-side tool. It is passed as a raw
// option array (the legacy alias is `web_search_preview`). The Responses API
// runs the search itself and returns a `web_search_call` output item next to
// the assistant message, which the result converter skips.
$result = $platform->invoke('gpt-4o', $messages, [
    'tools' => [['type' => 'web_search']],
]);

echo $result->asText().\PHP_EOL;

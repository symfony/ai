<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenResponses\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// Same web_search scenario as web-search.php, but driven through the generic
// OpenResponses bridge pointed at the canonical OpenAI Responses endpoint. This
// exercises the OpenResponses result converter against a real `web_search_call`
// output item, proving both converters skip it instead of aborting.
$platform = Factory::createPlatform('https://api.openai.com', env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser('What is the latest stable Symfony version? Use web search to be sure.'),
);

$result = $platform->invoke('gpt-4o', $messages, [
    'tools' => [['type' => 'web_search']],
]);

echo $result->asText().\PHP_EOL;

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\WebSearchResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$agent = new Agent($platform, 'claude-sonnet-4-5-20250929');

$messages = new MessageBag(
    Message::ofUser('What is the current 12 month Euribor rate?'),
);

$result = $agent->call($messages, [
    'server_tools' => [
        'web_search' => ['max_uses' => 3],
    ],
]);

foreach ($result->asMultiPart() as $part) {
    echo match (true) {
        $part instanceof WebSearchResult => "<search query=\"{$part->getQuery()}\" status=\"{$part->getStatus()}\">\n",
        default => $part->getContent()."\n",
    };
}

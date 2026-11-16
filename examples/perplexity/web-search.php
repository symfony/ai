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

$messages = new MessageBag(Message::ofUser('What is the best French cheese?'));
$result = $platform->invoke('perplexity/sonar', $messages, [
    'tools' => [[
        'type' => 'web_search',
        'filters' => [
            // Perplexity expects bare domains here, not full URLs with a path.
            'search_domain_filter' => ['wikipedia.org'],
            'search_recency_filter' => 'month',
        ],
    ]],
    // Offering the tool lets the model decide whether to search; this forces it
    'tool_choice' => ['type' => 'web_search'],
]);

echo $result->asText().\PHP_EOL;
echo \PHP_EOL;

print_search_results($result->getMetadata()->get('search_results', []));
print_citations($result->getMetadata()->get('citations', []));

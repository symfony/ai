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

$messages = new MessageBag(Message::ofUser('What is the best French cheese of the first quarter-century of 21st century?'));
$result = $platform->invoke('perplexity/sonar', $messages, [
    'tools' => [[
        'type' => 'web_search',
        'filters' => [
            'search_domain_filter' => ['sciencedirect.com', 'ncbi.nlm.nih.gov', 'researchgate.net'],
            'search_after_date_filter' => '01/01/2000',
            'search_before_date_filter' => '01/01/2025',
        ],
    ]],
    // Offering the tool lets the model decide whether to search; this forces it
    'tool_choice' => ['type' => 'web_search'],
]);

echo $result->asText().\PHP_EOL;
echo \PHP_EOL;

print_search_results($result->getMetadata()->get('search_results', []));
print_citations($result->getMetadata()->get('citations', []));

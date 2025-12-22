<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Perplexity\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = PlatformFactory::create(env('PERPLEXITY_API_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('What is the best French cheese?'));
$result = $platform->invoke('sonar', $messages, [
    'search_domain_filter' => [
        'https://en.wikipedia.org/wiki/Cheese',
    ],
    'search_mode' => 'web',
    'enable_search_classifier' => true,
    'search_recency_filter' => 'week',
]);

echo $result->asText().\PHP_EOL;
echo \PHP_EOL;

print_search_results($result->getMetadata());
print_citations($result->getMetadata());

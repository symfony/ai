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
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('PERPLEXITY_API_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser(
        new DocumentUrl('https://upload.wikimedia.org/wikipedia/commons/2/20/Re_example.pdf'),
        'What is this document about?',
    ),
);
$result = $platform->invoke('sonar', $messages);

echo $result->asText().\PHP_EOL;

perplexity_print_search_results($result->getMetadata());
perplexity_print_citations($result->getMetadata());

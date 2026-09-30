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
use Symfony\AI\Agent\Bridge\Firecrawl\Firecrawl;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$firecrawl = new Firecrawl(
    http_client(),
    env('FIRECRAWL_API_KEY'),
    'https://api.firecrawl.dev',
);

$toolbox = new Toolbox([$firecrawl], logger: logger());

$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox, includeSources: true);

$messages = new MessageBag(Message::ofUser('Search the web for the Symfony Messenger component documentation and list the three most relevant pages with one sentence each.'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL.\PHP_EOL;

print_sources($result->getMetadata()->get('sources'));

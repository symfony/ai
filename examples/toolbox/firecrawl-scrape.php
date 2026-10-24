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
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$firecrawl = new Firecrawl(
    http_client(),
    env('FIRECRAWL_API_KEY'),
    'https://api.firecrawl.dev',
);

$toolbox = new Toolbox([$firecrawl], logger: logger());

$agent = new Agent($platform, 'claude-sonnet-4-5-20250929', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('Scrape the following URL: https://symfony.com/doc/current/setup.html then resume it in less than 200 words.'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;

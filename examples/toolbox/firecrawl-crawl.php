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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$firecrawl = new Firecrawl(
    http_client(),
    env('FIRECRAWL_API_KEY'),
    env('FIRECRAWL_HOST'),
);

$toolbox = new Toolbox([$firecrawl], logger: logger());
$toolProcessor = new AgentProcessor($toolbox);

$agent = new Agent($platform, 'gpt-4o-mini', inputProcessors: [$toolProcessor], outputProcessors: [$toolProcessor]);

$messages = new MessageBag(Message::ofUser('Crawl the following URL: https://symfony.com/doc/current/setup.html then resume it in less than 200 words.'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

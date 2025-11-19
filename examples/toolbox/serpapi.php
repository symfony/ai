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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\Clock;
use Symfony\AI\Agent\Toolbox\Tool\Scraper;
use Symfony\AI\Agent\Toolbox\Tool\SerpApi;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\Clock as SymfonyClock;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$clock = new Clock(new SymfonyClock());
$crawler = new Scraper(http_client());
$serpApi = new SerpApi(http_client(), env('SERP_API_KEY'));
$toolbox = new Toolbox([$clock, $crawler, $serpApi], logger: logger());
$processor = new AgentProcessor($toolbox, includeSources: true);
$agent = new Agent($platform, 'gpt-4o', [$processor], [$processor]);

$prompt = <<<PROMPT
    Summarize the latest game of the Dallas Cowboys. When and where was it? Who was the opponent, what was the result,
    and how was the game and the weather in the city. Use tools for the research and only answer based on information
    given in the context - don't make up information.
    PROMPT;

$result = $agent->call(new MessageBag(Message::ofUser($prompt)));

echo $result->getContent().\PHP_EOL.\PHP_EOL;

echo 'Used sources:'.\PHP_EOL;
foreach ($result->getMetadata()->get('sources', []) as $source) {
    echo sprintf(' - %s (%s)', $source->getName(), $source->getReference()).\PHP_EOL;
}
echo \PHP_EOL;

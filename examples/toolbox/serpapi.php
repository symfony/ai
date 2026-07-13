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
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\Bridge\Scraper\Scraper;
use Symfony\AI\Agent\Bridge\SerpApi\SerpApi;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$crawler = new Scraper(http_client());
$serpApi = new SerpApi(http_client(), env('SERP_API_KEY'));
$toolbox = new Toolbox([new Clock(clock()), $crawler, $serpApi], logger: logger());
$agent = new Agent($platform, 'gpt-5.2', toolbox: $toolbox, includeSources: true);

$prompt = <<<PROMPT
    Summarize the latest game of the Dallas Cowboys. When and where was it? Who was the opponent, what was the result,
    and how was the game and the weather in the city. Use tools for the research and only answer based on information
    given in the context - don't make up information.
    PROMPT;

$result = $agent->call(new MessageBag(Message::ofUser($prompt)), options: ['stream' => true]);

foreach ($result->getContent() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta;
    }
}

echo \PHP_EOL.\PHP_EOL;

print_sources($result->getMetadata()->get('sources'));

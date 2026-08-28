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
use Symfony\AI\Agent\Bridge\Brave\Brave;
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\Bridge\Scraper\Scraper;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\Clock as SymfonyClock;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$brave = new Brave(http_client(), env('BRAVE_API_KEY'));
$clock = new Clock(new SymfonyClock());
$crawler = new Scraper(http_client());
$toolbox = new Toolbox([$brave, $clock, $crawler], logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox, includeSources: true);

$prompt = <<<PROMPT
    What is the latest blog post of the PHP Foundation about? Give its title, publication date and a short summary
    of its content. Use tools for the research and only answer based on information given in the context - don't
    make up information.
    PROMPT;

$result = $agent->call(new MessageBag(Message::ofUser($prompt)));

// asText() narrows the answer of reasoning models, a MultiPartResult of reasoning and message parts, to its text
echo $result->asText().\PHP_EOL.\PHP_EOL;

print_sources($result->getMetadata()->get('sources'));

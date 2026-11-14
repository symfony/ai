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
use Symfony\AI\Agent\Bridge\SpecStream\SpecStream;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$specStream = new SpecStream(http_client(), env('SPECSTREAM_API_KEY'));
$toolbox = new Toolbox([new Clock(clock()), $specStream], logger: logger());
$agent = new Agent($platform, 'gpt-5.2', toolbox: $toolbox, includeSources: true);

$prompt = <<<PROMPT
    What changed in the latest Symfony release? Name the version and its release date, and list the most important
    changes. Use tools for the research and only answer based on information given in the context - don't make up
    information.
    PROMPT;

$result = $agent->call(new MessageBag(Message::ofUser($prompt)));

echo $result->asText().\PHP_EOL.\PHP_EOL;

print_sources($result->getMetadata()->get('sources'));

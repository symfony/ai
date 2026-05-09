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
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

#[AsTool('list_european_capitals', 'Returns the capitals of a few European countries.')]
final class EuropeanCapitalsTool
{
    /**
     * @return list<string>
     */
    public function __invoke(): array
    {
        return ['Paris', 'Berlin', 'Madrid', 'Rome'];
    }
}

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

$toolbox = new Toolbox([new EuropeanCapitalsTool()], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gemini-2.5-flash', [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('Use the available tool to answer.'),
    Message::ofUser('List a few European capitals.'),
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

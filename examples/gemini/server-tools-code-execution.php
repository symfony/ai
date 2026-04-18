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
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

$toolbox = new Toolbox([], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gemini-3.1-pro-preview', [$processor], [$processor]);

$messages = new MessageBag(
    Message::ofUser('Calculate total cost of a mortgage with 1% interest on 100k€ principal with 25 year maturity'),
);

$result = $agent->call($messages, [
    'server_tools' => ['code_execution' => true],
]);

assert($result instanceof MultiPartResult);

foreach ($result as $part) {
    echo match (true) {
        $part instanceof ExecutableCodeResult => "<code>\n".$part->getContent()."\n</code>\n\n",
        default => $part->getContent()."\n",
    };
}

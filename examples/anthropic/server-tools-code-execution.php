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
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$agent = new Agent($platform, 'claude-sonnet-4-5-20250929');

$messages = new MessageBag(
    Message::ofUser('Calculate total cost of a mortgage with 1% interest on 100k€ principal with 25 year maturity'),
);

$result = $agent->call($messages, [
    'tools' => [[
        'type' => 'code_execution_20250825',
        'name' => 'code_execution',
    ]],
]);

assert($result instanceof MultiPartResult);

foreach ($result as $part) {
    echo match (true) {
        $part instanceof ExecutableCodeResult => "<code>\n".$part->getContent()."\n</code>\n\n",
        default => $part->getContent()."\n",
    };
}

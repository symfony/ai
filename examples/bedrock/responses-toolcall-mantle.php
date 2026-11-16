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
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: optional_env('AWS_BEARER_TOKEN_BEDROCK'),
    region: env('AWS_DEFAULT_REGION'),
    api: 'responses',
    httpClient: http_client(),
);

$toolbox = new Toolbox([new Multiply()], logger: logger());
$agent = new Agent($platform, 'google.gemma-4-31b', toolbox: $toolbox);

$result = $agent->call(new MessageBag(Message::ofUser('What is 6 multiplied by 7?')));

echo $result->getContent().\PHP_EOL;

/**
 * @author asrar <aszenz@gmail.com>
 */
#[AsTool('multiply', 'Multiply two integers.')]
final class Multiply
{
    public function __invoke(int $left, int $right): int
    {
        return $left * $right;
    }
}

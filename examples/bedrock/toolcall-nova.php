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
use Symfony\AI\Agent\Toolbox\Tool\Wikipedia;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Bedrock\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use validator\EnvValidator;

require_once dirname(__DIR__).'/bootstrap.php';

EnvValidator::validateAwsCredentials();

$platform = PlatformFactory::create();
$model = new Nova();

$wikipedia = new Wikipedia(http_client());
$toolbox = new Toolbox([$wikipedia]);
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor], logger());

$messages = new MessageBag(
    Message::ofUser('Who is the current chancellor of Germany? Use Wikipedia to find the answer.')
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

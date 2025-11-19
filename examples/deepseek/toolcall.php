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
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\DeepSeek\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('DEEPSEEK_API_KEY'), http_client());

$clock = new Clock();
$toolbox = new Toolbox([$clock]);
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'deepseek-chat', [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('How many days until next Christmas?'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

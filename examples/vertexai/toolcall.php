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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = PlatformFactory::create(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), httpClient: adc_aware_http_client());

$toolbox = new Toolbox([new Clock()], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gemini-2.5-flash-lite', [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('What time is it?'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

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
use Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('HUGGINGFACE_KEY'), Provider::NSCALE, http_client());

$wikipedia = new Wikipedia(http_client());
$toolbox = new Toolbox([$wikipedia], logger: logger());
$agent = new Agent($platform, 'Qwen/Qwen3-32B', toolbox: $toolbox);

$messages = new MessageBag(Message::ofUser('Who is the current chancellor of Germany?'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;

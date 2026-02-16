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
use Symfony\AI\Agent\Bridge\Ollama\Ollama;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OLLAMA_HOST_URL'), httpClient: http_client());

$ollama = new Ollama(http_client(), env('OLLAMA_API_KEY'));
$toolbox = new Toolbox([$ollama], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, env('OLLAMA_LLM'), [$processor], [$processor]);

$result = $agent->call(new MessageBag(Message::ofUser('What is Ollama?')));

echo $result->getContent().\PHP_EOL;

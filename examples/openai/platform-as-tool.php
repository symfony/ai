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
use Symfony\AI\Agent\Toolbox\Tool\Platform;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory as ElevenLabsPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// Create the main OpenAI platform
$openAiPlatform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Create ElevenLabs platform as a tool for speech-to-text
$elevenLabsPlatform = ElevenLabsPlatformFactory::create(
    apiKey: env('ELEVEN_LABS_API_KEY'),
    httpClient: http_client()
);

// Wrap ElevenLabs platform as a tool
$speechToText = new Platform($elevenLabsPlatform, 'scribe_v1');

// Create toolbox with the platform tool
$toolbox = new Toolbox([$speechToText], logger: logger());
$processor = new AgentProcessor($toolbox);

// Create agent with OpenAI platform but with ElevenLabs tool available
$agent = new Agent($openAiPlatform, 'gpt-4o-mini', [$processor], [$processor], logger: logger());

// The agent can now use ElevenLabs for speech-to-text while using OpenAI for reasoning
$audioPath = dirname(__DIR__, 2).'/fixtures/audio.mp3';
$messages = new MessageBag(
    Message::ofUser('I have an audio file. Please transcribe it and tell me what it says.'),
    Message::ofUser(Audio::fromFile($audioPath)),
);

$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

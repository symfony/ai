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
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$elevenLabsPlatform = PlatformFactory::create(
    apiKey: env('ELEVEN_LABS_API_KEY'),
    httpClient: http_client(),
);

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), httpClient: http_client());

$agent = new Agent($platform, 'gpt-4o');
$answer = $agent->call(new MessageBag(
    Message::ofUser('Hello'),
));

$result = $platform->invoke('eleven_multilingual_v2', new Text('Hello world'), [
    'voice' => 'Dslrhjl3ZpzrctukrQSN', // Brad (https://elevenlabs.io/app/voice-library?voiceId=Dslrhjl3ZpzrctukrQSN)
]);

echo $answer->asVoice();

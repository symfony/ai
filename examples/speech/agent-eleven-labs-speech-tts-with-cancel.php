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
use Symfony\AI\Agent\Speech\SpeechConfiguration;
use Symfony\AI\Agent\SpeechAgent;
use Symfony\AI\Platform\Bridge\ElevenLabs\Factory as ElevenLabsFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;

require_once dirname(__DIR__).'/bootstrap.php';

$openAIPlatform = OpenAiFactory::createPlatform(env('OPENAI_API_KEY'), httpClient: http_client());
$agent = new Agent($openAIPlatform, 'gpt-4o');

$elevenLabsPlatform = ElevenLabsFactory::createPlatform(
    apiKey: env('ELEVEN_LABS_API_KEY'),
    httpClient: http_client(),
);

$speechAgent = new SpeechAgent($agent, new SpeechConfiguration(
    ttsModel: 'eleven_multilingual_v2',
    ttsOptions: [
        'voice' => 'pqHfZKP75CvOlQylNhV4', // Bill
    ],
    ttsStream: true,
), textToSpeechPlatform: $elevenLabsPlatform);

$answer = $speechAgent->call(new MessageBag(
    Message::ofUser('Tell me a long story about the history of the Symfony framework.'),
));

assert($answer instanceof StreamResult);

echo $answer->getMetadata()->get('text').\PHP_EOL;

$audio = '';
$chunks = 0;

foreach ($answer->getContent() as $delta) {
    if (!$delta instanceof BinaryDelta) {
        continue;
    }

    $audio .= $delta->getData();
    ++$chunks;

    // Simulate a barge-in: stop the TTS stream after 5 chunks
    // (e.g., the user started speaking, the UI was closed, etc.).
    if (5 === $chunks) {
        $answer->cancel();
    }
}

file_put_contents('/tmp/speech-partial.mp3', $audio);

output()->writeln(sprintf('Stopped after <comment>%d</comment> chunks (cancelled: %s)', $chunks, $answer->isCancelled() ? 'yes' : 'no'));
output()->writeln('Partial audio saved to <comment>/tmp/speech-partial.mp3</comment>');

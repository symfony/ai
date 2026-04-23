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
use Symfony\AI\Agent\Speech\SpeechSession;
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

$session = new SpeechSession($speechAgent);

// First user input: start a long answer.
$firstAnswer = $session->call(new MessageBag(
    Message::ofUser('Tell me a long story about the history of the Symfony framework.'),
));

assert($firstAnswer instanceof StreamResult);

$firstChunks = 0;
foreach ($firstAnswer->getContent() as $delta) {
    if (!$delta instanceof BinaryDelta) {
        continue;
    }

    ++$firstChunks;

    // After 3 chunks, the user changes their mind and sends a new input.
    // SpeechSession::call() automatically cancels the previous StreamResult.
    if (3 === $firstChunks) {
        $secondAnswer = $session->call(new MessageBag(
            Message::ofUser('Actually, just tell me the year Symfony was first released.'),
        ));
        assert($secondAnswer instanceof StreamResult);
        break;
    }
}

$secondAudio = '';
$secondChunks = 0;
foreach ($secondAnswer->getContent() as $delta) {
    if (!$delta instanceof BinaryDelta) {
        continue;
    }

    $secondAudio .= $delta->getData();
    ++$secondChunks;
}

file_put_contents('/tmp/speech-session.mp3', $secondAudio);

output()->writeln(sprintf('First answer stopped after <comment>%d</comment> chunks (cancelled: %s)', $firstChunks, $firstAnswer->isCancelled() ? 'yes' : 'no'));
output()->writeln(sprintf('Second answer completed in <comment>%d</comment> chunks', $secondChunks));
output()->writeln('Second answer audio saved to <comment>/tmp/speech-session.mp3</comment>');

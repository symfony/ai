<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\E2E;

/**
 * The microphone of the browser is faked by Chrome, which loops the audio fixture of the monorepo,
 * see E2ETestCase. Without it, the speech-to-text model would only receive silence.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpeechTest extends E2ETestCase
{
    public function testTalkingToTheBotIsAnsweredWithAudio()
    {
        $this->visit('/speech');
        $this->assertSelectorTextContains('#welcome h4', 'Speech Bot');

        $this->click('#micro-start');
        $this->client->waitForVisibility('#micro-stop');

        // Give the fake microphone time to record the whole audio fixture.
        sleep(4);

        $this->click('#micro-stop');
        $this->waitForElementCount('#chat-body audio', 1);

        // The question is transcribed by the speech-to-text model and shown as user message.
        $this->assertSelectorExists('#chat-body .user-message');

        // The answer of the agent is converted to speech, and played back as audio element.
        $this->assertSelectorExists('#chat-body audio[src^="data:audio"]');

        $panel = $this->openAiPanel(platformCalls: 3);

        // Transcription, the agent itself, and the text-to-speech conversion of its answer.
        $panel->assertMetrics(platformCalls: 3, tools: 3);

        $panel->assertPlatformCall('whisper-1', tokenUsage: false);
        $panel->assertPlatformCall('gpt-5-mini');
        $panel->assertPlatformCall('tts-1', tokenUsage: false);

        // The blog agent is registered as subagent, next to the clock tool.
        $panel->assertToolRegistered('symfony_blog');
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

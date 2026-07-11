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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class YouTubeTest extends E2ETestCase
{
    /**
     * The video ID the demo suggests in the UI itself.
     */
    private const string VIDEO_ID = '6uXW-ulpj0s';

    public function testChatAboutTheTranscriptOfAVideo()
    {
        $this->visit('/youtube');
        $this->assertSelectorTextContains('#welcome h4', 'Chat about a YouTube Video');

        $this->type('#youtube-id', self::VIDEO_ID);
        $this->click('#chat-start');

        // Loading the transcript does not call the model yet, the chat is only initialized with it.
        $this->assertStringContainsString('What do you want to know about that video?', $this->waitForBotMessage());

        $this->chat('Summarize the video in one sentence.');

        $this->assertNotSame('', trim($this->waitForBotMessage(2)));

        $panel = $this->openAiPanel();

        $panel->assertMetrics(platformCalls: 1, toolCalls: 0);
        $panel->assertPlatformCall('gpt-5-mini');

        // Without tools, the agent answers in a single call - the transcript is handed over to the
        // model as system message.
        $calls = $panel->platformCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('System:', $calls[0]['input']);
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

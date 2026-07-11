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
final class StreamTest extends E2ETestCase
{
    /**
     * The input of this chat has no id, unlike the other ones.
     */
    private const string INPUT = '.card-footer input';

    public function testAnswerIsStreamedIntoTheChat()
    {
        $this->visit('/stream');
        $this->assertSelectorTextContains('#welcome h4', 'Turbo Stream Chat');

        $this->chat('Which technologies are used in this example?', self::INPUT);

        // The live component re-renders with the stream source, and Turbo removes it again as soon
        // as the answer is complete.
        $this->waitForElementCount('#bot-message-stream');
        $this->waitForRemoval('#bot-message-stream');

        $answer = $this->crawler()->filter('#bot-message-streamed')->text();

        $this->assertNotSame('Thinking...', $answer);
        $this->assertStringContainsString('Symfony', $answer);

        // The answer is streamed as markdown, and rendered as HTML on the fly.
        $this->assertSelectorExists('#bot-message-streamed p');

        // The agent is called in the streaming request, the last one that hits the profiler.
        $panel = $this->openAiPanel();

        $panel->assertMetrics(platformCalls: 1, toolCalls: 0);
        $panel->assertPlatformCall('gpt-4.1');

        // Without tools, the agent answers in a single call.
        $calls = $panel->platformCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('stream', $calls[0]['options']);
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

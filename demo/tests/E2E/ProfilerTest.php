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
 * Verifies the profiler integration of the AI bundle itself: the toolbar block, and the sections of
 * the Symfony AI panel. The movie bot is used as example, because it exercises an agent, a tool, and
 * structured output in a single request.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ProfilerTest extends E2ETestCase
{
    public function testToolbarAndPanelReportTheAgentCall()
    {
        $this->visit('/movies');
        $this->chat('Tell me about Inception, do you have it in the collection?');
        $this->waitForBotMessage();

        // The toolbar is replaced with the one of the live component request, which called the agent.
        $this->client->waitForVisibility('.sf-toolbar-block-ai');

        $this->assertSelectorTextContains('.sf-toolbar-block-ai .sf-toolbar-value', '2');
        $this->assertSelectorExists('.sf-toolbar-block-ai a[href*="panel=ai"]');

        $panel = $this->openAiPanel(platformCalls: 2);

        $this->assertSelectorTextContains('#collector-content', 'Platform Calls');
        $this->assertSelectorTextContains('#collector-content', 'Tools');
        $this->assertSelectorTextContains('#collector-content', 'Tool Calls');

        $calls = $panel->platformCalls();
        $this->assertCount(2, $calls);

        // First call: the conversation, resulting in the tool call of the agent.
        $this->assertSame('gpt-4.1', $calls[0]['model']);
        $this->assertStringContainsString('System:', $calls[0]['input']);
        $this->assertStringContainsString('User:', $calls[0]['input']);
        $this->assertStringContainsString('movie_search', $calls[0]['options']);
        $this->assertStringContainsString('Tool call', $calls[0]['result']);

        // Second call: the result of the tool is sent back to the model.
        $this->assertStringContainsString('Tool:', $calls[1]['input']);

        // The platform reports the token usage of every call back as metadata.
        foreach ($calls as $call) {
            $this->assertStringContainsString('token_usage', $call['result']);
        }

        // The tool of the agent, and the actual call of it, are listed in their own sections.
        $panel->assertToolRegistered('movie_search');
        $this->assertSelectorTextContains('#collector-content', 'Arguments');
    }

    public function testToolbarShowsNoAiBlockWithoutPlatformCall()
    {
        $this->client->waitForVisibility('.sf-toolbar-block-config');

        $this->assertSelectorNotExists('.sf-toolbar-block-ai');
    }

    public function testPanelIsEmptyForRequestsWithoutPlatformCall()
    {
        $panel = $this->openAiPanel(platformCalls: 0);

        $this->assertSelectorTextContains('#collector-content .empty', 'No platform calls were made.');
        $this->assertSame([], $panel->platformCalls());
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

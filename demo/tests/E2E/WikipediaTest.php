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
final class WikipediaTest extends E2ETestCase
{
    public function testResearchIsGroundedInWikipediaSources()
    {
        $this->visit('/wikipedia');
        $this->assertSelectorTextContains('#welcome h4', 'Wikipedia Research');

        $this->chat('What is the Symfony framework? Answer in one sentence.');

        // What the model answers is up to the articles it reads, so only the grounding is asserted:
        // an answer about the topic, and the sources the agent used for it.
        $this->assertStringContainsString('Symfony', $this->waitForBotMessage());

        // The agent is configured with include_sources, so its sources are listed as badges.
        $this->assertSelectorExists('#chat-body .badge.text-bg-secondary');
        $this->assertSelectorExists('#chat-body a[href*="wikipedia.org"]');

        $panel = $this->openAiPanel(platformCalls: 2);

        $panel->assertMetrics(platformCalls: 2, tools: 2, toolCalls: 1);
        $panel->assertPlatformCall('gpt-5-mini');
        $panel->assertToolRegistered('wikipedia_search');
        $panel->assertToolRegistered('wikipedia_article');

        // The prompt of the agent is configured with include_tools, so the tools are part of it.
        $this->assertStringContainsString('wikipedia', $panel->platformCalls()[0]['options']);
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

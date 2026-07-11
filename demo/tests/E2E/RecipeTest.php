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
final class RecipeTest extends E2ETestCase
{
    public function testRecipeIsStreamedAsStructuredOutputAndCanBeShared()
    {
        $this->visit('/recipe');
        $this->assertSelectorTextContains('#chat-body h4', 'Cooking Recipes');

        $this->chat('A quick vegetarian pasta recipe, please.');

        // The card appears as soon as the first chunk of the structured output arrives, and Turbo
        // removes the stream source when the recipe is complete.
        $this->waitForElementCount('#recipe-stream-source');
        $this->waitForElementCount('#recipe-stream-target h2');
        $this->waitForRemoval('#recipe-stream-source');

        $this->assertNotSame('', trim($this->crawler()->filter('#recipe-stream-target h2')->text()));
        $this->assertSelectorExists('#recipe-stream-target .badge');
        $this->assertSelectorTextContains('#recipe-stream-target', 'Ingredients');
        $this->assertSelectorTextContains('#recipe-stream-target', 'Instructions');

        // The agent is called in the streaming request, the last one that hits the profiler.
        $panel = $this->openAiPanel();

        $panel->assertMetrics(platformCalls: 1, toolCalls: 0);
        $panel->assertPlatformCall('gpt-5-mini');

        // Without tools, the agent answers in a single call.
        $calls = $panel->platformCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('stream', $calls[0]['options']);

        // Back on the recipe, which is restored from the cache, it can be shared by email.
        $this->client->request('GET', '/recipe');
        $this->click('button[data-live-action-param="openShare"]');
        $this->client->waitForVisibility('#recipe-share-overlay.is-open');

        $this->type('#recipe-share-overlay input[type="email"]', 'chef@example.com');
        $this->click('#recipe-share-overlay form button');

        $this->waitForRemoval('#recipe-share-overlay.is-open');
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

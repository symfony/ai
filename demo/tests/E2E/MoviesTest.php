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
final class MoviesTest extends E2ETestCase
{
    public function testChatSuggestsMoviesFromTheCollection()
    {
        $this->visit('/movies');
        $this->assertSelectorTextContains('#welcome h4', 'Chat about movies');

        $this->chat('Tell me about Inception, do you have it in the collection?');

        $this->assertNotSame('', trim($this->waitForBotMessage()));

        // The structured output of the agent is rendered as movie cards next to the answer.
        $this->waitForElementCount('.movie-suggestions .movie-suggestion');
        $this->assertSelectorTextContains('.movie-suggestions', 'Inception');

        $panel = $this->openAiPanel(platformCalls: 2);

        // The agent calls the platform twice: once resulting in the tool call, once with its result.
        $panel->assertMetrics(platformCalls: 2, tools: 1, toolCalls: 1);
        $panel->assertPlatformCall('gpt-4.1');
        $panel->assertToolRegistered('movie_search');
        $this->assertStringContainsString('movie_search', $panel->platformCalls()[0]['result']);

        // Back on the chat, which is restored from the session, a card opens the movie details.
        $this->client->request('GET', '/movies');
        $this->click('.movie-suggestions .movie-poster-btn');
        $this->client->waitForVisibility('.movie-modal-panel');

        $this->assertSelectorTextContains('.movie-modal-panel', 'Inception');
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

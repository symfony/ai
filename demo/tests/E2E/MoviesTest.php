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

use PHPUnit\Framework\Attributes\Before;

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

        $panel = $this->openAiPanel(platformCalls: 3);

        // Two calls of the agent, plus the vectorization of the search query by the movie search.
        $panel->assertMetrics(platformCalls: 3, tools: 3, toolCalls: 1);
        $panel->assertPlatformCall('gpt-6-luna');
        $panel->assertToolRegistered('movie_search');
        $panel->assertToolRegistered('search_by_director');
        $this->assertStringContainsString('movie_search', $panel->platformCalls()[0]['result']);

        $embedding = array_values(array_filter($panel->platformCalls(), static fn (array $call) => 'text-embedding-ada-002' === $call['model']));
        $this->assertCount(1, $embedding, 'Vectorization of the search query in Symfony AI panel');

        // Back on the chat, which is restored from the session, a card opens the movie details.
        $this->client->request('GET', '/movies');
        $this->click('.movie-suggestions .movie-poster-btn');
        $this->client->waitForVisibility('.movie-modal-panel');

        $this->assertSelectorTextContains('.movie-modal-panel', 'Inception');

        // The director in the details asks for their movies as the next prompt, which closes the details.
        $this->click('.movie-modal-panel .movie-name');
        $this->waitForRemoval('.movie-modal-overlay');
        $this->waitForBotMessage(2);

        $this->assertSame('Christopher Nolan', $this->textOf('#chat-body .user-message:not(.loading)', 1));
    }

    #[Before]
    protected function ensureLoadedMovies(): void
    {
        if (!Store::isAvailable()) {
            $this->markTestSkipped('The database of the demo is not reachable, start it with "docker compose up -d".');
        }

        if (0 === Store::movies()) {
            Store::loadMovies();
        }
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

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

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Component\Process\Process;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MoviesTest extends E2ETestCase
{
    private ?Process $worker = null;

    public function testAnswerIsStreamedToTheBrowserViaMercure()
    {
        $this->startWorker();

        $this->visit('/movies');
        $this->assertSelectorTextContains('#welcome h4', 'Chat about movies');

        $this->chat('Which movies of Christopher Nolan do you have?');

        // Mercure pushes each tool call of the agent while the answer is streamed
        $this->waitForElementCount('#movie-steps .movie-tool-call');
        $this->assertSelectorTextContains('#movie-steps', 'search_by_director');

        $this->waitForRemoval('#movie-answering');
        $this->assertNotSame('', trim($this->waitForBotMessage()));

        // The agent narrated its searches before running them, which the log keeps next to the tool calls
        $this->assertSelectorExists('#chat-body .research-log .research-step-commentary');
        $this->assertSelectorExists('#chat-body .research-log .movie-tool-call');
        $this->waitForElementCount('.movie-suggestions .movie-suggestion');
        $this->assertSelectorTextContains('.movie-suggestions', 'Inception');

        // Back on the chat, which is restored from the message store, a card opens the movie details.
        $this->client->request('GET', '/movies');
        $this->click('.movie-suggestions .movie-poster-btn');
        $this->client->waitForVisibility('.movie-modal-panel');

        $this->assertSelectorTextContains('.movie-modal-panel', 'Nolan');

        // The director in the details asks for their movies as the next prompt, which closes the details.
        $this->click('.movie-modal-panel .movie-name');
        $this->waitForRemoval('.movie-modal-overlay');
        $this->waitForBotMessage(2);

        $this->assertSame('Christopher Nolan', $this->textOf('#chat-body .user-message:not(.loading)', 1));
    }

    #[After]
    protected function stopWorker(): void
    {
        $this->worker?->stop();
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

    /**
     * The answer is streamed by a Messenger worker, which `symfony serve` would start - Panther's web
     * server does not.
     */
    private function startWorker(): void
    {
        Environment::expose();

        $this->worker = new Process(['php', 'bin/console', 'messenger:consume', 'async', '--time-limit='.self::AI_TIMEOUT], Environment::projectDirectory());
        $this->worker->start();
    }
}

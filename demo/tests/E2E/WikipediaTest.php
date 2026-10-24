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
use Symfony\Component\Process\Process;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class WikipediaTest extends E2ETestCase
{
    private ?Process $worker = null;

    public function testResearchIsPushedToTheBrowserViaMercure()
    {
        $this->startWorker();

        $this->visit('/wikipedia');
        $this->assertSelectorTextContains('#welcome h4', 'Wikipedia Research');

        $this->chat('What is the Symfony framework? Answer in one sentence.');

        // The worker narrates each step before taking it, and Mercure pushes it while the research runs.
        $this->waitForElementCount('#research-steps .research-step-commentary');
        $this->waitForElementCount('#research-steps .research-step-tool');

        // What the model answers is up to the articles it reads, so only the grounding is asserted:
        // an answer about the topic, and the sources the agent used for it.
        $this->waitForRemoval('#research');
        $this->assertStringContainsString('Symfony', $this->waitForBotMessage());

        $this->assertSelectorExists('#chat-body .research-log .research-step-tool');
        $this->assertSelectorExists('#chat-body .message-sources .source-pill');
        $this->assertSelectorExists('#chat-body a[href*="wikipedia.org"]');
    }

    #[After]
    protected function stopWorker(): void
    {
        $this->worker?->stop();
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }

    /**
     * The research runs in a Messenger worker, which `symfony serve` would start - Panther's web
     * server does not.
     */
    private function startWorker(): void
    {
        Environment::expose();

        $this->worker = new Process(['php', 'bin/console', 'messenger:consume', 'async', '--time-limit='.self::AI_TIMEOUT], Environment::projectDirectory());
        $this->worker->start();
    }
}

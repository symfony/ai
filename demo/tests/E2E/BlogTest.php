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
final class BlogTest extends E2ETestCase
{
    public function testRetrievalAugmentedGenerationOnTheSymfonyBlog()
    {
        $this->visit('/blog');
        $this->assertSelectorTextContains('#welcome h4', 'Retrieval Augmented Generation');

        $this->chat('What happened in the last week of Symfony?');

        $this->assertNotSame('', trim($this->waitForBotMessage()));

        // The agent is prompted to link the blog posts it used as sources.
        $this->assertSelectorExists('#chat-body .bot-message a');

        $panel = $this->openAiPanel(platformCalls: 3);

        // Two calls of the agent, plus the vectorization of the query by the similarity search.
        $panel->assertMetrics(platformCalls: 3, tools: 2, toolCalls: 1);
        $panel->assertPlatformCall('gpt-4.1');
        $panel->assertToolRegistered('similarity_search');
        $panel->assertToolRegistered('clock');

        $embedding = array_values(array_filter($panel->platformCalls(), static fn (array $call) => 'text-embedding-ada-002' === $call['model']));
        $this->assertCount(1, $embedding, 'Vectorization of the search query in Symfony AI panel');
        $this->assertStringContainsString('Vector with', $embedding[0]['result']);
    }

    /**
     * The store is set up and indexed on demand, so that the use case does not depend on someone
     * having run the indexer beforehand.
     *
     * An existing index is left alone: the test needs *an* index, not a fresh one, and re-indexing
     * would embed the whole blog through OpenAI again. StoreTest is the one rebuilding it.
     */
    #[Before]
    protected function ensureIndexedStore(): void
    {
        if (!Store::isAvailable()) {
            $this->markTestSkipped('The database of the demo is not reachable, start it with "docker compose up -d".');
        }

        Store::setup();

        if (0 === Store::documents()) {
            Store::index();
        }
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}

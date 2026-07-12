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
use PHPUnit\Framework\TestCase;

/**
 * The indexing pipeline of the blog, driven through the console commands of the Store component:
 * the RSS feed of the Symfony blog is loaded, filtered, split, embedded by OpenAI and written into
 * the pgvector store - which is then searched again.
 *
 * Every stage of that has unit tests of its own in the Store component; what they cannot cover is
 * the composition, wired by the `ai.yaml` of this application against the real services. Hence no
 * browser here: this drives the console, and leaves the store indexed for the use cases.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StoreTest extends TestCase
{
    public function testTheBlogIsIndexedAndCanBeSearchedAgain()
    {
        // The indexer writes a fresh id per chunk, so indexing an indexed store duplicates it
        // instead of refreshing it - the store is rebuilt from scratch to get a known state.
        // With `StoreInterface::clear()` this becomes a clear, keeping the table and its index.
        Store::drop();
        Store::setup();

        $this->assertSame(0, Store::documents(), 'The store is empty after being set up.');

        Store::index();

        $documents = Store::documents();
        $this->assertGreaterThan(0, $documents, 'The indexer embedded the blog posts into the store.');

        // The store is searched the same way the `similarity_search` tool of the blog agent does.
        $output = Store::retrieve('Week of Symfony');

        $this->assertStringContainsString('Result #1', $output);
        $this->assertStringContainsString('Score', $output);

        // Searching does not change what is stored - the use cases run against this very store.
        $this->assertSame($documents, Store::documents());
    }

    public function testSettingUpAnIndexedStoreKeepsItIntact()
    {
        Store::setup();

        if (0 === Store::documents()) {
            Store::index();
        }

        $documents = Store::documents();
        $this->assertGreaterThan(0, $documents);

        // `setup()` issues its statements with `IF NOT EXISTS`, so it is safe to run on an indexed
        // store - which is what BlogTest relies on to index only when the store is empty.
        Store::setup();

        $this->assertSame($documents, Store::documents());
    }

    #[Before]
    protected function skipWithoutStore(): void
    {
        if (!Store::isAvailable()) {
            $this->markTestSkipped('The database of the demo is not reachable, start it with "docker compose up -d".');
        }

        if (!Environment::isConfigured('OPENAI_API_KEY')) {
            $this->markTestSkipped('Set OPENAI_API_KEY in .env.local, or in your environment, to embed the blog posts.');
        }
    }
}

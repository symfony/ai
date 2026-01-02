<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfileIndexer;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfileIndexerTest extends TestCase
{
    private ProfileIndexer $indexer;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->indexer = new ProfileIndexer();
        $this->fixtureDir = __DIR__.'/../../Fixtures/profiler';
    }

    public function testIndexProfilesReturnsEmptyArrayWhenFileNotFound()
    {
        $profiles = $this->indexer->indexProfiles('/non/existent/dir');

        $this->assertSame([], $profiles);
    }

    public function testIndexProfilesParsesValidCsv()
    {
        $profiles = $this->indexer->indexProfiles($this->fixtureDir);

        $this->assertCount(3, $profiles);

        $first = $profiles[0];
        $this->assertSame('abc123', $first->token);
        $this->assertSame('127.0.0.1', $first->ip);
        $this->assertSame('GET', $first->method);
        $this->assertSame('/api/users', $first->url);
        $this->assertSame(200, $first->statusCode);
    }

    public function testFilterByMethod()
    {
        $profiles = $this->indexer->indexProfiles($this->fixtureDir);
        $filtered = $this->indexer->filterByMethod($profiles, 'POST');

        $this->assertCount(1, $filtered);
        $this->assertSame('def456', array_values($filtered)[0]->token);
    }

    public function testFilterByStatusCode()
    {
        $profiles = $this->indexer->indexProfiles($this->fixtureDir);
        $filtered = $this->indexer->filterByStatusCode($profiles, 404);

        $this->assertCount(1, $filtered);
        $this->assertSame('ghi789', array_values($filtered)[0]->token);
    }

    public function testFilterByUrl()
    {
        $profiles = $this->indexer->indexProfiles($this->fixtureDir);
        $filtered = $this->indexer->filterByUrl($profiles, 'users');

        $this->assertCount(2, $filtered);
    }

    public function testFilterByIp()
    {
        $profiles = $this->indexer->indexProfiles($this->fixtureDir);
        $filtered = $this->indexer->filterByIp($profiles, '127.0.0.1');

        $this->assertCount(2, $filtered);
    }
}

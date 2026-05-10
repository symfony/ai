<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\PageNotFoundException;
use Symfony\AI\Mate\Bridge\Knowledge\Service\ChunkBuilder;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KnowledgeCache;
use Symfony\AI\Mate\Bridge\Knowledge\Service\TocBuilder;
use Symfony\AI\Mate\Bridge\Knowledge\Tests\Fixtures\FixtureProvider;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class KnowledgeCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/ai_mate_knowledge_test_'.uniqid();
        mkdir($this->cacheDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    public function testEnsureBuildsTocAndChunkArtifacts()
    {
        $cache = $this->createCache();
        $provider = $this->createProvider();

        $cache->ensure($provider);

        $this->assertFileExists($this->cacheDir.'/fixture/toc.json');
        $this->assertFileExists($this->cacheDir.'/fixture/chunks.json');
    }

    public function testGetTocReturnsTreeWithExpectedShape()
    {
        $cache = $this->createCache();
        $provider = $this->createProvider();

        $toc = $cache->getToc($provider);

        $this->assertSame('Sample Documentation', $toc->getTitle());
        $this->assertCount(2, $toc->getChildren());
    }

    public function testGetChunksForPathReturnsOnlyPageChunks()
    {
        $cache = $this->createCache();
        $provider = $this->createProvider();

        $chunks = $cache->getChunksForPath($provider, 'setup');

        foreach ($chunks as $chunk) {
            $this->assertSame('setup', $chunk->getPath());
        }
        $this->assertNotEmpty($chunks);
    }

    public function testGetChunksForPathThrowsWhenPageMissing()
    {
        $cache = $this->createCache();
        $provider = $this->createProvider();

        $this->expectException(PageNotFoundException::class);

        $cache->getChunksForPath($provider, 'does/not/exist');
    }

    public function testEnsureRebuildsCacheWhenOlderThanTtl()
    {
        $cache = new KnowledgeCache($this->cacheDir, new TocBuilder(), new ChunkBuilder(), ttlSeconds: 1);
        $provider = $this->createProvider();

        $cache->ensure($provider);
        $tocFile = $this->cacheDir.'/fixture/toc.json';
        // Backdate the cache file so it appears stale on the next ensure() call.
        $past = time() - 3600;
        touch($tocFile, $past);
        clearstatcache(true, $tocFile);

        $cache->ensure($provider);

        clearstatcache(true, $tocFile);
        $this->assertGreaterThan($past, filemtime($tocFile));
    }

    public function testEnsureKeepsCacheWhenWithinTtl()
    {
        $cache = new KnowledgeCache($this->cacheDir, new TocBuilder(), new ChunkBuilder(), ttlSeconds: 86400);
        $provider = $this->createProvider();

        $cache->ensure($provider);
        $tocFile = $this->cacheDir.'/fixture/toc.json';
        $originalMtime = filemtime($tocFile);

        $cache->ensure($provider);

        clearstatcache(true, $tocFile);
        $this->assertSame($originalMtime, filemtime($tocFile));
    }

    public function testTtlOfZeroDisablesAutoRebuild()
    {
        $cache = new KnowledgeCache($this->cacheDir, new TocBuilder(), new ChunkBuilder(), ttlSeconds: 0);
        $provider = $this->createProvider();

        $cache->ensure($provider);
        $tocFile = $this->cacheDir.'/fixture/toc.json';
        touch($tocFile, time() - 86400 * 30);
        clearstatcache(true, $tocFile);
        $stableMtime = filemtime($tocFile);

        $cache->ensure($provider);

        clearstatcache(true, $tocFile);
        $this->assertSame($stableMtime, filemtime($tocFile));
    }

    private function createCache(): KnowledgeCache
    {
        return new KnowledgeCache($this->cacheDir, new TocBuilder(), new ChunkBuilder());
    }

    private function createProvider(): FixtureProvider
    {
        return new FixtureProvider(\dirname(__DIR__).'/Fixtures/docs');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory.\DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}

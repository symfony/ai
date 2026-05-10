<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Service;

use Symfony\AI\Mate\Bridge\Knowledge\Exception\PageNotFoundException;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\SyncFailedException;
use Symfony\AI\Mate\Bridge\Knowledge\Model\PageChunk;
use Symfony\AI\Mate\Bridge\Knowledge\Model\TocNode;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\DocsProviderInterface;

/**
 * Orchestrates fetch + index for a knowledge provider and caches the results
 * (toc.json, chunks.json) on disk for fast tool calls.
 *
 * Layout:
 *   {cacheDir}/{provider}/docs/      cloned source tree
 *   {cacheDir}/{provider}/toc.json   serialized TocNode tree
 *   {cacheDir}/{provider}/chunks.json flat list of PageChunk
 *
 * Cache freshness: when the on-disk artifacts are older than $ttlSeconds, the
 * next call to {@see ensure()} transparently re-syncs (git pull) and rebuilds
 * the index. Pass 0 to disable the TTL and only sync once.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class KnowledgeCache
{
    public function __construct(
        private string $cacheDir,
        private TocBuilder $tocBuilder,
        private ChunkBuilder $chunkBuilder,
        private int $ttlSeconds = 86400,
    ) {
    }

    /**
     * Ensures the provider is cloned and indexed. Idempotent.
     *
     * - If no cache exists, clones the source and builds the index.
     * - If the cache exists but is older than the TTL, re-syncs and rebuilds.
     */
    public function ensure(DocsProviderInterface $provider): void
    {
        $tocFile = $this->tocFile($provider);

        if (file_exists($tocFile) && !$this->isStale($tocFile)) {
            return;
        }

        $providerDir = $this->providerDir($provider);
        $entryPoint = $provider->sync($providerDir);

        $this->buildIndex($provider, $entryPoint);
    }

    public function getToc(DocsProviderInterface $provider): TocNode
    {
        $this->ensure($provider);

        $tocFile = $this->tocFile($provider);
        $raw = file_get_contents($tocFile);
        if (false === $raw) {
            throw new SyncFailedException(\sprintf('Could not read TOC cache "%s".', $tocFile));
        }

        /** @var array{path: string, title: string, has_content: bool, children: list<array<string, mixed>>} $data */
        $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        return TocNode::fromArray($data);
    }

    /**
     * @return list<PageChunk>
     */
    public function getChunks(DocsProviderInterface $provider): array
    {
        $this->ensure($provider);

        $chunksFile = $this->chunksFile($provider);
        $raw = file_get_contents($chunksFile);
        if (false === $raw) {
            throw new SyncFailedException(\sprintf('Could not read chunks cache "%s".', $chunksFile));
        }

        /** @var list<array{path: string, page_title: string, section_title: string, depth: int, content: string}> $data */
        $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        return array_map(static fn (array $row) => PageChunk::fromArray($row), $data);
    }

    /**
     * @return list<PageChunk>
     */
    public function getChunksForPath(DocsProviderInterface $provider, string $path): array
    {
        $matches = array_values(array_filter(
            $this->getChunks($provider),
            static fn (PageChunk $chunk) => $chunk->getPath() === $path,
        ));

        if ([] === $matches) {
            throw new PageNotFoundException(\sprintf('Page "%s" not found in provider "%s".', $path, $provider->getName()));
        }

        return $matches;
    }

    public function docsDir(DocsProviderInterface $provider): string
    {
        return $this->providerDir($provider).'/docs';
    }

    private function isStale(string $tocFile): bool
    {
        if ($this->ttlSeconds <= 0) {
            return false;
        }

        $mtime = @filemtime($tocFile);
        if (false === $mtime) {
            return true;
        }

        return (time() - $mtime) >= $this->ttlSeconds;
    }

    private function providerDir(DocsProviderInterface $provider): string
    {
        return rtrim($this->cacheDir, '/').'/'.$provider->getName();
    }

    private function tocFile(DocsProviderInterface $provider): string
    {
        return $this->providerDir($provider).'/toc.json';
    }

    private function chunksFile(DocsProviderInterface $provider): string
    {
        return $this->providerDir($provider).'/chunks.json';
    }

    private function buildIndex(DocsProviderInterface $provider, string $entryPoint): void
    {
        $docsRoot = \dirname($entryPoint);

        $toc = $this->tocBuilder->build($entryPoint, $docsRoot);
        $chunks = $this->chunkBuilder->build($toc, $docsRoot);

        $this->writeJson($this->tocFile($provider), $toc->toArray());
        $this->writeJson(
            $this->chunksFile($provider),
            array_map(static fn (PageChunk $chunk) => $chunk->toArray(), $chunks),
        );
    }

    /**
     * @param mixed $data
     */
    private function writeJson(string $file, $data): void
    {
        $dir = \dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new SyncFailedException(\sprintf('Could not create cache directory "%s".', $dir));
        }

        $encoded = json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        if (false === file_put_contents($file, $encoded)) {
            throw new SyncFailedException(\sprintf('Could not write cache file "%s".', $file));
        }
    }
}

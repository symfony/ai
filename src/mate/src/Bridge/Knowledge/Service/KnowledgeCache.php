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

use Symfony\AI\Mate\Bridge\Knowledge\Exception\InvalidProviderNameException;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\PageNotFoundException;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\SyncFailedException;
use Symfony\AI\Mate\Bridge\Knowledge\Model\PageChunk;
use Symfony\AI\Mate\Bridge\Knowledge\Model\TocNode;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\DocsProviderInterface;

/**
 * Orchestrates fetch + index for a knowledge provider and caches the results
 * (toc.json, chunks.json, metadata.json) on disk for fast tool calls.
 *
 * Layout:
 *   {cacheDir}/{provider}/docs/         cloned source tree
 *   {cacheDir}/{provider}/toc.json      serialized TocNode tree
 *   {cacheDir}/{provider}/chunks.json   flat list of PageChunk
 *   {cacheDir}/{provider}/metadata.json provider, synced_at, chunk_count, revision
 *   {cacheDir}/{provider}/.lock         flock file for concurrent ensure() calls
 *
 * Cache freshness: when the on-disk artifacts are older than $ttlSeconds, the
 * next call to {@see ensure()} transparently re-syncs (git pull) and rebuilds
 * the index. Pass 0 to disable the TTL and only sync once.
 *
 * Concurrency: ensure() uses an exclusive flock on a per-provider lock file
 * so concurrent processes do not clobber each other's cache build. JSON
 * artifacts are written to a temp file and atomically renamed into place.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class KnowledgeCache
{
    /**
     * Provider names are used as directory components inside the cache dir, so
     * they must not contain anything that could be interpreted as a path or
     * shell metacharacter. We accept the same characters as a Composer-style
     * package suffix: lowercase letters, digits, hyphen and underscore.
     */
    private const PROVIDER_NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

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
        $this->ensureDirectory($providerDir);

        $lockHandle = $this->acquireLock($providerDir);

        try {
            // Double-checked: another process may have rebuilt while we waited
            // for the lock.
            clearstatcache(true, $tocFile);
            if (file_exists($tocFile) && !$this->isStale($tocFile)) {
                return;
            }

            $entryPoint = $provider->sync($providerDir);

            $this->buildIndex($provider, $entryPoint);
        } finally {
            $this->releaseLock($lockHandle);
        }
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
        return rtrim($this->cacheDir, '/').'/'.$this->validateProviderName($provider->getName());
    }

    private function validateProviderName(string $name): string
    {
        if (1 !== preg_match(self::PROVIDER_NAME_PATTERN, $name)) {
            throw new InvalidProviderNameException(\sprintf('Knowledge provider name "%s" is invalid. Allowed characters: lowercase letters, digits, "-" and "_"; must start with a letter or digit; max 64 characters.', $name));
        }

        return $name;
    }

    private function tocFile(DocsProviderInterface $provider): string
    {
        return $this->providerDir($provider).'/toc.json';
    }

    private function chunksFile(DocsProviderInterface $provider): string
    {
        return $this->providerDir($provider).'/chunks.json';
    }

    private function metadataFile(DocsProviderInterface $provider): string
    {
        return $this->providerDir($provider).'/metadata.json';
    }

    private function lockFile(string $providerDir): string
    {
        return $providerDir.'/.lock';
    }

    /**
     * @return resource
     */
    private function acquireLock(string $providerDir)
    {
        $lockFile = $this->lockFile($providerDir);
        $handle = @fopen($lockFile, 'c');
        if (false === $handle) {
            throw new SyncFailedException(\sprintf('Could not open lock file "%s".', $lockFile));
        }

        if (!flock($handle, \LOCK_EX)) {
            fclose($handle);
            throw new SyncFailedException(\sprintf('Could not acquire exclusive lock on "%s".', $lockFile));
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function releaseLock($handle): void
    {
        flock($handle, \LOCK_UN);
        fclose($handle);
    }

    private function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new SyncFailedException(\sprintf('Could not create cache directory "%s".', $dir));
        }
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
        $this->writeJson(
            $this->metadataFile($provider),
            $this->buildMetadata($provider, $docsRoot, \count($chunks)),
        );
    }

    /**
     * @return array{provider: string, synced_at: string, chunk_count: int, docs_dir: string, revision: ?string}
     */
    private function buildMetadata(DocsProviderInterface $provider, string $docsRoot, int $chunkCount): array
    {
        return [
            'provider' => $provider->getName(),
            'synced_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'chunk_count' => $chunkCount,
            'docs_dir' => $docsRoot,
            'revision' => $this->readRevision($docsRoot),
        ];
    }

    private function readRevision(string $docsRoot): ?string
    {
        $candidates = [
            $docsRoot.'/.git/HEAD',
            \dirname($docsRoot).'/.git/HEAD',
        ];

        foreach ($candidates as $head) {
            if (!is_file($head)) {
                continue;
            }

            $content = @file_get_contents($head);
            if (false === $content) {
                continue;
            }

            $content = trim($content);
            if (str_starts_with($content, 'ref: ')) {
                $ref = substr($content, 5);
                $refFile = \dirname($head).'/'.$ref;
                if (is_file($refFile)) {
                    $hash = @file_get_contents($refFile);
                    if (false !== $hash) {
                        return trim($hash);
                    }
                }

                return $content;
            }

            return $content;
        }

        return null;
    }

    private function writeJson(string $file, mixed $data): void
    {
        $this->ensureDirectory(\dirname($file));

        $encoded = json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        $tmp = $file.'.tmp.'.bin2hex(random_bytes(6));
        if (false === @file_put_contents($tmp, $encoded)) {
            throw new SyncFailedException(\sprintf('Could not write cache file "%s".', $tmp));
        }

        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new SyncFailedException(\sprintf('Could not move cache file "%s" to "%s".', $tmp, $file));
        }
    }
}

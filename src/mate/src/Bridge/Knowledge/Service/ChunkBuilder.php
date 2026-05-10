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

use Symfony\AI\Mate\Bridge\Knowledge\Model\PageChunk;
use Symfony\AI\Mate\Bridge\Knowledge\Model\TocNode;
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\Loader\RstLoader;

/**
 * Walks a TOC tree and produces a flat list of {@see PageChunk} objects by
 * splitting each page at its RST heading boundaries.
 *
 * Reuses {@see RstLoader::loadContent()} so the chunking semantics (15K-char
 * overflow, section depth) stay consistent with the Store component.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ChunkBuilder
{
    public function __construct(
        private RstLoader $rstLoader = new RstLoader(),
    ) {
    }

    /**
     * @return list<PageChunk>
     */
    public function build(TocNode $toc, string $rootDir): array
    {
        $chunks = [];
        $this->collect($toc, $rootDir, $chunks);

        return $chunks;
    }

    /**
     * @param list<PageChunk> $chunks
     */
    private function collect(TocNode $node, string $rootDir, array &$chunks): void
    {
        if ($node->hasContent()) {
            foreach ($this->loadPage($node, $rootDir) as $chunk) {
                $chunks[] = $chunk;
            }
        }

        foreach ($node->getChildren() as $child) {
            $this->collect($child, $rootDir, $chunks);
        }
    }

    /**
     * @return iterable<PageChunk>
     */
    private function loadPage(TocNode $node, string $rootDir): iterable
    {
        $absolutePath = $this->resolveAbsolutePath($node->getPath(), $rootDir);

        if (null === $absolutePath || !file_exists($absolutePath)) {
            return;
        }

        $content = file_get_contents($absolutePath);
        if (false === $content) {
            return;
        }

        foreach ($this->rstLoader->loadContent($content, $absolutePath) as $document) {
            \assert($document instanceof EmbeddableDocumentInterface);
            $metadata = $document->getMetadata();
            $text = $metadata->getText() ?? '';

            if ('' === $text) {
                continue;
            }

            yield new PageChunk(
                $node->getPath(),
                $node->getTitle(),
                $metadata->getTitle() ?? '',
                $metadata->getDepth() ?? 0,
                $text,
            );
        }
    }

    private function resolveAbsolutePath(string $relativePath, string $rootDir): ?string
    {
        $rootDir = rtrim($rootDir, '/');

        $candidates = [
            $rootDir.'/'.$relativePath.'.rst',
            $rootDir.'/'.$relativePath.'/index.rst',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

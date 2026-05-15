<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Capability;

use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\Mate\Bridge\Knowledge\Model\PageChunk;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\ProviderRegistry;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KnowledgeCache;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ReadTool
{
    /**
     * Caps to keep MCP responses bounded. The total budget is the soft ceiling
     * for the whole page; per-section caps protect against single huge
     * sections eating the entire budget.
     */
    private const MAX_SECTIONS = 50;
    private const MAX_SECTION_CONTENT = 8000;
    private const MAX_TOTAL_CONTENT = 60000;

    public function __construct(
        private ProviderRegistry $registry,
        private KnowledgeCache $cache,
    ) {
    }

    /**
     * @param string $provider Provider name as returned by knowledge-providers (e.g. "symfony")
     * @param string $path     Page path as returned by knowledge-toc (e.g. "setup/web_server_configuration")
     */
    #[McpTool(name: 'knowledge-read', description: 'Read a documentation page. Returns the page split into RST sections so the agent can pick the relevant chunk.')]
    public function read(string $provider, string $path): string
    {
        $providerService = $this->registry->get($provider);
        $chunks = $this->cache->getChunksForPath($providerService, $path);

        $pageTitle = $chunks[0]->getPageTitle();

        $totalSections = \count($chunks);
        $emittedSections = [];
        $totalContent = 0;
        $sectionTruncated = false;
        $pageTruncated = false;

        foreach ($chunks as $index => $chunk) {
            if ($index >= self::MAX_SECTIONS) {
                $pageTruncated = true;
                break;
            }

            $content = $chunk->getContent();
            if (\strlen($content) > self::MAX_SECTION_CONTENT) {
                $content = substr($content, 0, self::MAX_SECTION_CONTENT);
                $sectionTruncated = true;
            }

            $totalContent += \strlen($content);
            if ($totalContent > self::MAX_TOTAL_CONTENT) {
                $overflow = $totalContent - self::MAX_TOTAL_CONTENT;
                $content = substr($content, 0, max(0, \strlen($content) - $overflow));
                $emittedSections[] = $this->toArray($chunk, $content);
                $pageTruncated = true;
                break;
            }

            $emittedSections[] = $this->toArray($chunk, $content);
        }

        $payload = [
            'provider' => $provider,
            'path' => $path,
            'title' => $pageTitle,
            'sections' => $emittedSections,
        ];

        if ($pageTruncated || $sectionTruncated) {
            $payload['truncated'] = [
                'page' => $pageTruncated,
                'sections' => $sectionTruncated,
                'total_sections' => $totalSections,
                'returned_sections' => \count($emittedSections),
            ];
        }

        return ResponseEncoder::encode($payload);
    }

    /**
     * @return array{title: string, depth: int, content: string}
     */
    private function toArray(PageChunk $chunk, string $content): array
    {
        return [
            'title' => $chunk->getSectionTitle(),
            'depth' => $chunk->getDepth(),
            'content' => $content,
        ];
    }
}

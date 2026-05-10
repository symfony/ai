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

        return ResponseEncoder::encode([
            'provider' => $provider,
            'path' => $path,
            'title' => $pageTitle,
            'sections' => array_map(static fn (PageChunk $chunk) => [
                'title' => $chunk->getSectionTitle(),
                'depth' => $chunk->getDepth(),
                'content' => $chunk->getContent(),
            ], $chunks),
        ]);
    }
}

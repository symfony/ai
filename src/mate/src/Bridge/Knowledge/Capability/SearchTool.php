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
use Symfony\AI\Mate\Bridge\Knowledge\Provider\ProviderRegistry;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KnowledgeCache;
use Symfony\AI\Mate\Bridge\Knowledge\Service\SearcherInterface;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SearchTool
{
    /**
     * Hard ceiling on the number of results regardless of what the caller asks
     * for. Keeps MCP responses small and predictable.
     */
    private const MAX_LIMIT = 50;
    private const DEFAULT_LIMIT = 20;

    public function __construct(
        private ProviderRegistry $registry,
        private KnowledgeCache $cache,
        private SearcherInterface $searcher,
    ) {
    }

    /**
     * @param string $provider Provider name as returned by knowledge-providers (e.g. "symfony")
     * @param string $query    Case-insensitive substring to search for
     * @param int    $limit    Maximum results to return (default 20, capped at 50)
     */
    #[McpTool(name: 'knowledge-search', description: 'Substring search across a provider\'s documentation chunks. Use this to discover relevant pages when you don\'t know where to start, then refine with knowledge-read.')]
    public function search(string $provider, string $query, int $limit = self::DEFAULT_LIMIT): string
    {
        $providerService = $this->registry->get($provider);
        $chunks = $this->cache->getChunks($providerService);

        $effectiveLimit = max(1, min(self::MAX_LIMIT, $limit));

        return ResponseEncoder::encode([
            'provider' => $provider,
            'query' => $query,
            'limit' => $effectiveLimit,
            'results' => $this->searcher->search($chunks, $query, $effectiveLimit),
        ]);
    }
}

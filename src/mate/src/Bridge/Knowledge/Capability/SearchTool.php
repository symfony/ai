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
use Symfony\AI\Mate\Bridge\Knowledge\Service\KeywordSearcher;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KnowledgeCache;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SearchTool
{
    public function __construct(
        private ProviderRegistry $registry,
        private KnowledgeCache $cache,
        private KeywordSearcher $searcher,
    ) {
    }

    /**
     * @param string $provider Provider name as returned by knowledge-providers (e.g. "symfony")
     * @param string $query    Case-insensitive substring to search for
     * @param int    $limit    Maximum results to return (default 20)
     */
    #[McpTool(name: 'knowledge-search', description: 'Substring search across a provider\'s documentation chunks. Use this to discover relevant pages when you don\'t know where to start, then refine with knowledge-read.')]
    public function search(string $provider, string $query, int $limit = 20): string
    {
        $providerService = $this->registry->get($provider);
        $chunks = $this->cache->getChunks($providerService);

        return ResponseEncoder::encode([
            'provider' => $provider,
            'query' => $query,
            'results' => $this->searcher->search($chunks, $query, $limit),
        ]);
    }
}

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
use Symfony\AI\Mate\Bridge\Knowledge\Exception\PageNotFoundException;
use Symfony\AI\Mate\Bridge\Knowledge\Model\TocNode;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\ProviderRegistry;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KnowledgeCache;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TocTool
{
    public function __construct(
        private ProviderRegistry $registry,
        private KnowledgeCache $cache,
    ) {
    }

    /**
     * @param string|null $provider Provider name to browse (e.g. "symfony"). Omit to list all available providers.
     * @param string|null $path     Optional path of the TOC node to expand (e.g. "setup"). Omit for the provider's root.
     */
    #[McpTool(name: 'knowledge-toc', description: 'Browse documentation. Without arguments, lists all registered providers. With a provider, returns its table of contents at the given path (or the root if no path is given). The first call for a provider triggers a one-time clone of its source repository; subsequent calls are served from the local cache and refreshed automatically when stale.')]
    public function browse(?string $provider = null, ?string $path = null): string
    {
        if (null === $provider || '' === $provider) {
            return $this->listProviders();
        }

        return $this->browseProvider($provider, $path);
    }

    private function listProviders(): string
    {
        $providers = [];
        foreach ($this->registry->all() as $registered) {
            $providers[] = [
                'name' => $registered->getName(),
                'title' => $registered->getTitle(),
                'description' => $registered->getDescription(),
                'format' => $registered->getFormat(),
            ];
        }

        return ResponseEncoder::encode([
            'providers' => $providers,
            'usage' => 'Call knowledge-toc again with one of the provider names to browse its table of contents.',
        ]);
    }

    private function browseProvider(string $provider, ?string $path): string
    {
        $providerService = $this->registry->get($provider);
        $root = $this->cache->getToc($providerService);

        $node = null === $path || '' === $path
            ? $root
            : $root->findByPath($path);

        if (null === $node) {
            throw new PageNotFoundException(\sprintf('TOC path "%s" not found in provider "%s".', $path ?? '', $provider));
        }

        return ResponseEncoder::encode([
            'provider' => $provider,
            'path' => $node->getPath(),
            'title' => $node->getTitle(),
            'has_content' => $node->hasContent(),
            'children' => array_map(static fn (TocNode $child) => [
                'path' => $child->getPath(),
                'title' => $child->getTitle(),
                'has_content' => $child->hasContent(),
                'has_children' => [] !== $child->getChildren(),
            ], $node->getChildren()),
        ]);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store;

/**
 * Decorator that wraps an IndexerInterface with a pre-configured default source.
 *
 * This is useful for bundle configuration where the source is defined in YAML/PHP config
 * but can still be overridden at runtime by passing a source to index().
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConfiguredIndexer implements IndexerInterface
{
    /**
     * @param string|array<string>|null $defaultSource Default source to use when none is provided to index()
     */
    public function __construct(
        private IndexerInterface $indexer,
        private string|array|null $defaultSource = null,
    ) {
    }

    public function index(string|iterable|null $source = null, array $options = []): void
    {
        $this->indexer->index($source ?? $this->defaultSource, $options);
    }
}

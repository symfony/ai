<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Indexer;

use Symfony\AI\Store\IndexerInterface;

/**
 * Decorator that wraps a SourceIndexer with a pre-configured default source.
 *
 * This is useful for bundle configuration where the source is defined in YAML/PHP config
 * but can still be overridden at runtime by passing a source to index().
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConfiguredSourceIndexer implements IndexerInterface
{
    /**
     * @param string|array<string> $defaultSource Default source to use when calling index()
     */
    public function __construct(
        private readonly SourceIndexer $indexer,
        private readonly string|array $defaultSource,
    ) {
    }

    public function index(string|iterable|object|null $input = null, array $options = []): void
    {
        $this->indexer->index($input ?? $this->defaultSource, $options);
    }
}
